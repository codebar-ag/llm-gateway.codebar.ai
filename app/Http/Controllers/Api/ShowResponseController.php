<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
use App\Services\QueueEstimate;
use App\Support\ForwardedHeaders;
use App\Support\QueuePlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowResponseController
{
    public function __invoke(Request $request, string $id, QueueEstimate $estimate): JsonResponse
    {
        $entry = BacklogEntry::query()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->findOrFail($id);

        // Recomputed on every poll rather than carried from the 202: the line
        // moves, and a position that does not is decoration.
        $place = $estimate->for($entry);

        return (new BacklogEntryResource($entry))
            ->withPlace($place)
            ->response($request)
            ->withHeaders(self::backoff($place));
    }

    /**
     * Tell a well-behaved client how long to wait before asking again.
     *
     * Polling is deliberately unthrottled, so this is the only thing standing
     * between a queued entry and a client asking about it in a tight loop.
     * Floored at a second: `Retry-After: 0` invites exactly that loop.
     *
     * @return array<string, string>
     */
    private static function backoff(?QueuePlace $place): array
    {
        if ($place === null) {
            return [];
        }

        return ['Retry-After' => (string) max(1, $place->estimatedSeconds)];
    }
}
