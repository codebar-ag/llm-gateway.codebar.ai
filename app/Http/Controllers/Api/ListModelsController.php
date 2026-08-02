<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\ResolveGateway;
use App\Services\Upstream\Upstreams;
use App\Support\ForwardedHeaders;
use App\Support\UpstreamCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * The one endpoint this app answers synchronously rather than through the
 * backlog. The queue exists to protect a backend's limited concurrent
 * generation slots; listing models is a cheap, idempotent read that never
 * touches one, and plenty of OpenAI-compatible clients call it before they
 * ever send a completion — a `202` and a poll loop would only get in their way.
 *
 * Cheap for the backend is not the same as free for the upstream. This route
 * sits outside `throttle:queue` for the reason above, is reachable through the
 * tunnel, and needs no caller key at all under the shipped default — so every
 * public request would otherwise be one more request LiteLLM has to answer.
 * The short cache below collapses a burst of them into one.
 */
class ListModelsController
{
    public function __invoke(Request $request, Upstreams $upstreams): JsonResponse
    {
        return UpstreamCall::json(self::for($request, $upstreams));
    }

    /**
     * The gateway's model list as data, cached per gateway *and* per caller.
     *
     * Shared with `ShowModelController`, which answers out of this same list,
     * so both endpoints hit the upstream once between them and — the part that
     * matters — neither can key the cache differently from the other. The key
     * carries the hash of the caller's key because that key is replayed
     * upstream and decides which models come back: a cache keyed on the
     * gateway alone would serve one caller's list to the next. Callers sending
     * no key share one entry, the same bucket they already share everywhere
     * else in this app.
     *
     * @return array<array-key, mixed>|JsonResponse
     */
    public static function for(Request $request, Upstreams $upstreams): array|JsonResponse
    {
        $gateway = ResolveGateway::of($request);
        $driver = $upstreams->for($gateway);
        $headers = ForwardedHeaders::from($request);

        return UpstreamCall::cached(
            'models:'.$gateway->id.':'.(ForwardedHeaders::keyHash($request) ?? 'keyless'),
            Config::integer('gateway.api.models_cache_ttl'),
            fn () => $driver->models($gateway, $headers),
            fn (array $body) => $driver->normaliseModels($body),
        );
    }
}
