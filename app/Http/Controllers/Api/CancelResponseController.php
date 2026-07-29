<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
use App\Support\ForwardedHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelResponseController
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $entry = BacklogEntry::query()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->findOrFail($id);

        // A request already handed to the backend cannot be stopped cleanly.
        // The model decides it in one UPDATE rather than answering a question
        // here that a worker could invalidate before the write lands.
        if (! $entry->cancelIfQueued()) {
            return response()->json([
                'error' => [
                    'message' => "Not cancellable, status is '{$entry->refresh()->status->value}'",
                    'type' => 'invalid_state',
                ],
            ], 409);
        }

        return (new BacklogEntryResource($entry))->response($request);
    }
}
