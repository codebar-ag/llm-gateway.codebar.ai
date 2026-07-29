<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Middleware\ResolveGateway;
use App\Services\Backlog;
use App\Services\QueueEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueChatCompletionController
{
    public function __invoke(Request $request, Backlog $backlog, QueueEstimate $estimate): JsonResponse
    {
        // The body goes upstream exactly as it arrived.
        $entry = $backlog->add($request, $request->json()->all());

        return response()->json([
            'id' => $entry->id,
            'object' => 'chat.completion.queued',
            'status' => $entry->status->value,
            'model' => $entry->model,
            'created' => $entry->created_at->timestamp,
            'poll_url' => self::pollUrl($request, $entry->id),
            // The same block `/v1/responses` returns, from the same service:
            // both doors onto one queue have to quote one wait.
            'queue' => $estimate->for($entry)?->toArray(),
        ], 202);
    }

    /**
     * Answer under the prefix the caller used.
     *
     * Reads work either way, but a client that set its base URL to
     * `/{gateway}/v1` should not be handed a path that steps outside it.
     */
    private static function pollUrl(Request $request, string $id): string
    {
        if (! ResolveGateway::wasAddressed($request)) {
            return route('responses.show', $id, false);
        }

        return route('gateway.responses.show', [
            'gateway' => ResolveGateway::of($request)->getKey(),
            'id' => $id,
        ], false);
    }
}
