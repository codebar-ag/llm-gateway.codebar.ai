<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BacklogKind;
use App\Http\Middleware\ResolveGateway;
use App\Services\Backlog;
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
 *
 * Costing real compute is also why it is logged. Answering without a worker
 * is a routing decision, not a reason to leave the call out of the backlog:
 * `Backlog::record()` writes the entry the dashboard lists and filters it
 * from, and the vectors go back to the caller untouched either way.
 */
class CreateEmbeddingController
{
    public function __invoke(Request $request, Upstreams $upstreams, Backlog $backlog): JsonResponse
    {
        $gateway = ResolveGateway::of($request);
        $driver = $upstreams->for($gateway);
        $headers = ForwardedHeaders::from($request);
        $payload = $request->json()->all();

        return $backlog->record($request, BacklogKind::Embeddings, $payload, fn () => UpstreamCall::result(
            fn () => $driver->embeddings($gateway, $payload, $headers),
            fn (array $body) => $driver->normaliseEmbeddings($body),
        ));
    }
}
