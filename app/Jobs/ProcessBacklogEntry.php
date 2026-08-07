<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BacklogKind;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Upstream\UpstreamDriver;
use App\Services\Upstream\Upstreams;
use App\Support\ChatPayload;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Encrypted because the entry it points at decrypts a caller's bearer token:
 * the id alone carries no secret today, and the interface keeps it that way
 * if the payload ever grows.
 *
 * Unique until processing, not until finished — the lock has to be gone by
 * the time `failed()` runs, or the retry it dispatches would be swallowed as
 * a duplicate of the job that just failed.
 */
class ProcessBacklogEntry implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /** Retries are tracked on the entry itself, not by the queue. */
    public int $tries = 1;

    public function __construct(public readonly string $entryId)
    {
        $this->onQueue(Config::string('gateway.backlog.queue'));
    }

    public function uniqueId(): string
    {
        return $this->entryId;
    }

    public function uniqueFor(): int
    {
        return $this->timeout();
    }

    /**
     * Derived from the ceiling rather than the entry's own gateway: this has
     * to hold for every entry the worker might pick up, and the queue's
     * retry_after is a single value for the whole connection.
     */
    public function timeout(): int
    {
        return Config::integer('gateway.upstream.max_timeout') + 60;
    }

    public function handle(Upstreams $upstreams): void
    {
        $entry = BacklogEntry::find($this->entryId);

        // Not a guard but the claim itself: whoever loses the race stops here.
        if (! $entry?->claim()) {
            return;
        }

        // An inactive gateway still answers what it already accepted;
        // deactivating one stops new work, it does not strand queued work.
        $gateway = $entry->gateway ?? Gateway::default();

        if (! $gateway instanceof Gateway) {
            throw new RuntimeException('Entry '.$entry->id.' has no gateway and no default is configured.');
        }

        $driver = $upstreams->for($gateway);
        $headers = $entry->headers ?? [];

        // Nullable only since pruning, which touches nothing but finished
        // entries — and a finished entry never reaches a worker. The throw is
        // for the day that invariant breaks, not a path anything takes.
        $request = $entry->request
            ?? throw new RuntimeException('Entry '.$entry->id.' has no request body left to send.');

        /*
         | Which call this is, decided by the endpoint that queued it. All three
         | share the queue, the workers and the retry budget — that is the whole
         | point of routing them here rather than answering inline — but only a
         | chat completion has an answer that reduces to text. The other two are
         | stored whole because the caller polls for the body itself.
         */
        match ($entry->kind) {
            BacklogKind::Chat => $this->complete($entry, $request, $driver, $gateway, $headers),
            BacklogKind::Embeddings => $entry->markAnswered($driver->normaliseEmbeddings(
                self::body($driver->embeddings($gateway, $request, $headers)),
            )),
            BacklogKind::Moderations => $entry->markAnswered($driver->normaliseModerations(
                self::body($driver->moderations($gateway, $request, $headers)),
            )),
        };
    }

    /**
     * The chat path: normalise whatever the upstream speaks into a completion,
     * and store the assistant's text beside it.
     *
     * @param  array<string, mixed>  $request
     * @param  array<string, string>  $headers
     */
    private function complete(BacklogEntry $entry, array $request, UpstreamDriver $driver, Gateway $gateway, array $headers): void
    {
        $response = $driver->send($gateway, $request, $headers);
        $completion = $response->json();

        if (! is_array($completion)) {
            $entry->markCompleted($response->body(), []);

            return;
        }

        $completion = $driver->normalise($completion);

        $entry->markCompleted(ChatPayload::text($completion), $completion);
    }

    /**
     * A JSON body, or an empty one.
     *
     * An upstream that answered with something other than JSON has already
     * thrown by the time this runs — the drivers throw on any error status —
     * so this covers a 200 carrying a body the client could not decode, which
     * normalising into an empty answer states more honestly than a crash.
     *
     * @return array<array-key, mixed>
     */
    private static function body(Response $response): array
    {
        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    public function failed(Throwable $exception): void
    {
        $entry = BacklogEntry::find($this->entryId);

        if (! $entry) {
            return;
        }

        /*
         | Read before the entry reaches an ending: marking it failed drops the
         | forwarded headers, and the caller's own trace id lives in them. It is
         | what ties this line back to whatever the caller logged when they
         | queued — the entry id ties the other direction, since the 202 carried
         | it back to them.
         */
        $context = [
            'entry' => $entry->id,
            'gateway' => $entry->gateway_id,
            'model' => $entry->model,
            'request_id' => $entry->callerRequestId(),
        ];

        if (! $entry->canRetry()) {
            $entry->markFailed($exception);

            Log::error('Backlog entry failed permanently', [
                ...$context,
                'attempts' => $entry->attempts,
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        Log::warning('Backlog entry re-queued after a failed attempt', [
            ...$context,
            'attempt' => $entry->attempts,
            'reason' => $exception->getMessage(),
        ]);

        $entry->requeue();

        self::dispatch($entry->id)->delay(
            now()->addSeconds(Config::integer('gateway.backlog.retry_delay') * $entry->attempts)
        );
    }
}
