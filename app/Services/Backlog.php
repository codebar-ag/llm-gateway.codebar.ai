<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Http\Middleware\ResolveGateway;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Support\ForwardedHeaders;
use App\Support\UpstreamCall;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

class Backlog
{
    /**
     * Queue a request. Nothing here validates or rewrites the payload — the
     * model name is only read so the backlog can be filtered by it, and only
     * written back when the caller left it out and the gateway names one.
     *
     * The gateway is recorded, not looked up again later: the default can move
     * between a request being accepted and a worker running it, and the answer
     * has to come from the upstream the caller addressed.
     *
     * @param  array<string, mixed>  $payload  what the worker will send upstream
     */
    public function add(Request $request, array $payload): BacklogEntry
    {
        $gateway = ResolveGateway::of($request);

        $payload = self::withDefaultModel($payload, $gateway);

        $entry = BacklogEntry::create([
            'id' => BacklogEntry::newId(),
            'status' => BacklogStatus::Queued,
            'kind' => BacklogKind::Chat,
            'model' => self::modelName($payload),
            'gateway_id' => $gateway->getKey(),
            'request' => $payload,
            'headers' => ForwardedHeaders::from($request),
            'max_attempts' => Config::integer('gateway.backlog.max_attempts'),
            'api_key_hash' => ForwardedHeaders::keyHash($request),
        ]);

        ProcessBacklogEntry::dispatch($entry->id);

        return $entry;
    }

    /**
     * Run a call this app answers itself, and log it.
     *
     * The queue is not the only thing worth a row. An embedding costs the same
     * upstream compute a completion does and is billed the same way, so it is
     * recorded the same way — the difference being that it is already finished
     * by the time anything is written, so one insert states the whole entry
     * instead of a worker walking it from queued to done.
     *
     * The call is taken as a closure rather than its result so the timing is
     * measured here, at the only place that knows the entry has to carry it.
     *
     * @param  array<string, mixed>  $payload  what was sent upstream
     * @param  Closure(): (array<array-key, mixed>|JsonResponse)  $call
     */
    public function record(Request $request, BacklogKind $kind, array $payload, Closure $call): JsonResponse
    {
        $gateway = ResolveGateway::of($request);

        $startedAt = CarbonImmutable::now();
        $result = $call();
        $failed = $result instanceof JsonResponse;

        BacklogEntry::create([
            'id' => BacklogEntry::newId(),
            'status' => $failed ? BacklogStatus::Failed : BacklogStatus::Completed,
            'kind' => $kind,
            'model' => self::modelName($payload),
            'gateway_id' => $gateway->getKey(),
            'request' => $payload,
            // No headers. Every ending clears them — they exist so a worker can
            // replay the request, and this one has already been answered.
            'output' => $failed ? null : BacklogEntry::outputFor(self::answerFor($kind, $result)),
            'usage' => $failed || ! Arr::has($result, 'usage') ? null : BacklogEntry::usageFrom($result),
            'error' => $failed ? self::failureIn($result) : null,
            // One attempt of one: nothing retries a call the caller is holding
            // the connection open for.
            'attempts' => 1,
            'max_attempts' => 1,
            'api_key_hash' => ForwardedHeaders::keyHash($request),
            'started_at' => $startedAt,
            'finished_at' => CarbonImmutable::now(),
        ]);

        return UpstreamCall::json($result);
    }

    /**
     * What the entry stores as the answer.
     *
     * Embeddings are summarised rather than kept: a single batch is thousands
     * of floats per input, and storing them would grow the table by megabytes
     * a request to record something no operator reads and no caller comes back
     * for — they already have the vectors. Everything else is small enough to
     * keep verbatim, which is what the fallback does.
     *
     * @param  array<array-key, mixed>  $body
     */
    private static function answerFor(BacklogKind $kind, array $body): string
    {
        return match ($kind) {
            BacklogKind::Embeddings => self::embeddingsSummary($body),
            default => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        };
    }

    /** @param  array<array-key, mixed>  $body */
    private static function embeddingsSummary(array $body): string
    {
        $vectors = Arr::get($body, 'data');
        $vectors = is_array($vectors) ? $vectors : [];

        $first = Arr::get($body, 'data.0.embedding');
        $dimensions = is_array($first) ? count($first) : 0;

        return count($vectors).' '.str('embedding')->plural(count($vectors))
            .' × '.$dimensions.' '.str('dimension')->plural($dimensions);
    }

    /**
     * The upstream error, as the entry records it.
     *
     * `UpstreamCall` has already turned the failure into the answer the caller
     * gets, so it is read back out of that rather than described a second time
     * — an entry saying something the caller was not told would be worse than
     * no entry at all. The status is added because the response carries it in
     * a header the stored body does not, and `UpstreamFailure::describe()`
     * records it for a queued failure.
     *
     * @return array<string, mixed>
     */
    private static function failureIn(JsonResponse $response): array
    {
        $data = $response->getData(true);
        $error = is_array($data) ? Arr::get($data, 'error') : null;

        $described = ['status' => $response->getStatusCode()];

        foreach (is_array($error) ? $error : [] as $key => $value) {
            $described[(string) $key] = $value;
        }

        return $described;
    }

    /**
     * A gateway pointed at one model can name it, so a caller talking to a
     * single-model box does not have to. A payload that already says which
     * model it wants is left alone.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function withDefaultModel(array $payload, Gateway $gateway): array
    {
        if ($gateway->default_model === null || filled(Arr::get($payload, 'model'))) {
            return $payload;
        }

        return ['model' => $gateway->default_model] + $payload;
    }

    /** @param  array<string, mixed>  $payload */
    private static function modelName(array $payload): ?string
    {
        $model = Arr::get($payload, 'model');

        return is_scalar($model) ? (string) $model : null;
    }
}
