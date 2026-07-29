<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Upstream\Upstreams;
use App\Support\ChatPayload;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

        $response = $driver->send($gateway, $entry->request, $entry->headers ?? []);
        $completion = $response->json();

        if (! is_array($completion)) {
            $entry->markCompleted($response->body(), []);

            return;
        }

        $completion = $driver->normalise($completion);

        $entry->markCompleted(ChatPayload::text($completion), $completion);
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
