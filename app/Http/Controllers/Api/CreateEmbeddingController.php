<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\ResolveGateway;
use App\Services\Upstream\Upstreams;
use App\Support\ForwardedHeaders;
use App\Support\UpstreamCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Answered synchronously, the same as `ListModelsController` — no OpenAI
 * client expects to poll for an embedding, and unlike a model listing this
 * still costs the upstream real compute, so it shares `throttle:queue` with
 * everything that queues rather than going unthrottled like a plain read.
 */
class CreateEmbeddingController
{
    public function __invoke(Request $request, Upstreams $upstreams): JsonResponse
    {
        $gateway = ResolveGateway::of($request);
        $driver = $upstreams->for($gateway);
        $headers = ForwardedHeaders::from($request);
        $payload = $request->json()->all();

        return UpstreamCall::respond(
            fn () => $driver->embeddings($gateway, $payload, $headers),
            fn (array $body) => $driver->normaliseEmbeddings($body),
        );
    }
}
