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

/** Answered synchronously and logged all the same — see `CreateEmbeddingController`. */
class CreateModerationController
{
    public function __invoke(Request $request, Upstreams $upstreams, Backlog $backlog): JsonResponse
    {
        $gateway = ResolveGateway::of($request);
        $driver = $upstreams->for($gateway);
        $headers = ForwardedHeaders::from($request);
        $payload = $request->json()->all();

        return $backlog->record($request, BacklogKind::Moderations, $payload, fn () => UpstreamCall::result(
            fn () => $driver->moderations($gateway, $payload, $headers),
            fn (array $body) => $driver->normaliseModerations($body),
        ));
    }
}
