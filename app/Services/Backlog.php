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
     * Every endpoint that costs the upstream compute comes through here —
     * chat, embeddings and moderations — so there is exactly one queue, one
     * set of workers and one place a request is written down.
     *
     * @param  array<string, mixed>  $payload  what the worker will send upstream
     */
    public function add(Request $request, array $payload, BacklogKind $kind = BacklogKind::Chat): BacklogEntry
    {
        $gateway = ResolveGateway::of($request);

        $payload = self::withDefaultModel($payload, $gateway);

        $entry = BacklogEntry::create([
            'id' => BacklogEntry::newId(),
            'status' => BacklogStatus::Queued,
            'kind' => $kind,
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
