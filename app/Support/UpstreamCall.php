<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The error handling shared by every endpoint this app answers synchronously
 * rather than through the backlog — models, embeddings, moderations. There is
 * no worker here to catch a failed job and store it, so the upstream's answer
 * (or its absence) has to become a response right now.
 */
class UpstreamCall
{
    /**
     * @param  Closure(): Response  $send
     * @param  Closure(array<array-key, mixed>): array<array-key, mixed>  $normalise
     */
    public static function respond(Closure $send, Closure $normalise): JsonResponse
    {
        return self::json(self::result($send, $normalise));
    }

    /**
     * The same call as `respond()`, handed back as data instead of as a
     * response.
     *
     * An endpoint that wants one entry out of a list — `ShowModelController` —
     * would otherwise encode the entire upstream answer into a JsonResponse
     * only to decode it again and discard all but one element of it: three
     * passes over a list a LiteLLM instance can return hundreds of models in,
     * to answer with a single one.
     *
     * Failure stays a JsonResponse rather than becoming a second array shape.
     * An upstream error is already a finished answer carrying its own status,
     * and there is nothing in it for a caller to pick through — so the type
     * itself says "send this on unchanged".
     *
     * @param  Closure(): Response  $send
     * @param  Closure(array<array-key, mixed>): array<array-key, mixed>  $normalise
     * @return array<array-key, mixed>|JsonResponse the normalised body, or the error to answer with
     */
    public static function result(Closure $send, Closure $normalise): array|JsonResponse
    {
        try {
            $response = $send();
        } catch (RequestException $exception) {
            return self::upstreamError($exception);
        } catch (ConnectionException) {
            return self::unreachable();
        }

        $body = $response->json();

        return $normalise(is_array($body) ? $body : []);
    }

    /**
     * `result()`, remembered for `$ttl` seconds under `$key`.
     *
     * Only a success is stored. An upstream error and an unreachable upstream
     * are both a report about this instant, not a fact about the gateway, and
     * caching either one would keep answering with a failure the upstream has
     * already recovered from — for a read that is otherwise free to retry.
     *
     * The key has to identify the *caller*, not just the gateway. The caller's
     * Authorization header is replayed upstream (see `ForwardedHeaders`) and a
     * LiteLLM virtual key is scoped to its own set of models, so a key naming
     * only the gateway would hand one caller the model list belonging to
     * whoever happened to ask first. `ListModelsController::for()` is the one
     * place that key is built.
     *
     * A `$ttl` of zero switches the cache off entirely rather than storing
     * something already expired, which some stores treat as "forever".
     *
     * @param  Closure(): Response  $send
     * @param  Closure(array<array-key, mixed>): array<array-key, mixed>  $normalise
     * @return array<array-key, mixed>|JsonResponse
     */
    public static function cached(string $key, int $ttl, Closure $send, Closure $normalise): array|JsonResponse
    {
        if ($ttl <= 0) {
            return self::result($send, $normalise);
        }

        $cached = Cache::get($key);

        if (is_array($cached)) {
            /** @var array<array-key, mixed> $cached */
            return $cached;
        }

        $result = self::result($send, $normalise);

        if (! $result instanceof JsonResponse) {
            Cache::put($key, $result, $ttl);
        }

        return $result;
    }

    /** @param  array<array-key, mixed>|JsonResponse  $result */
    public static function json(array|JsonResponse $result): JsonResponse
    {
        return $result instanceof JsonResponse ? $result : response()->json($result);
    }

    /** The upstream's own error, stored verbatim — the same rule a queued entry's failure follows. */
    private static function upstreamError(RequestException $exception): JsonResponse
    {
        $response = $exception->response;
        $body = $response->json();

        return response()->json([
            'error' => [
                'message' => "Upstream responded with HTTP {$response->status()}",
                'type' => 'upstream_error',
                'body' => is_array($body) ? $body : $response->body(),
            ],
        ], $response->status());
    }

    private static function unreachable(): JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => 'Could not reach the upstream.',
                'type' => 'upstream_unreachable',
            ],
        ], 502);
    }
}
