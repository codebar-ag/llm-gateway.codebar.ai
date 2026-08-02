<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\ResolveGateway;
use App\Models\BacklogEntry;
use Illuminate\Http\Request;

/**
 * Where a caller collects the answer to what they just queued.
 *
 * Each kind is polled at the endpoint that posted it — an embedding at
 * `/v1/embeddings/{id}`, a completion at `/v1/responses/{id}` — so a client
 * only ever has to know the one base path it was configured with.
 *
 * Answered under the prefix the caller used, for the same reason
 * `QueueChatCompletionController` was: a client whose base URL is
 * `/{gateway}/v1` must not be handed a path that steps outside it.
 */
final class PollUrl
{
    public static function for(Request $request, BacklogEntry $entry): string
    {
        $route = $entry->kind->pollRoute();

        if (! ResolveGateway::wasAddressed($request)) {
            return route($route, $entry->id, false);
        }

        return route('gateway.'.$route, [
            'gateway' => ResolveGateway::of($request)->getKey(),
            'id' => $entry->id,
        ], false);
    }
}
