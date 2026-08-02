<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\ResolveGateway;
use App\Services\Upstream\Upstreams;
use App\Support\ForwardedHeaders;
use App\Support\UpstreamCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Answered synchronously — see `CreateEmbeddingController`. */
class CreateModerationController
{
    public function __invoke(Request $request, Upstreams $upstreams): JsonResponse
    {
        $gateway = ResolveGateway::of($request);
        $driver = $upstreams->for($gateway);
        $headers = ForwardedHeaders::from($request);
        $payload = $request->json()->all();

        return UpstreamCall::respond(
            fn () => $driver->moderations($gateway, $payload, $headers),
            fn (array $body) => $driver->normaliseModerations($body),
        );
    }
}
