<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BacklogKind;
use App\Models\BacklogEntry;
use App\Services\QueueEstimate;
use App\Support\ForwardedHeaders;
use App\Support\QueuedCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Collect a queued embedding.
 *
 * Answers `202` while it is still in line and the OpenAI embeddings body once
 * a worker has run it — the same body the upstream returned, so a client can
 * read `data[].embedding` without knowing a queue was ever involved.
 *
 * Narrowed to embeddings entries: an id from another endpoint reads as a 404
 * here rather than being handed back in a shape it was never meant to have.
 */
class ShowEmbeddingController
{
    public function __invoke(Request $request, string $id, QueueEstimate $estimate): JsonResponse
    {
        $entry = BacklogEntry::query()
            ->ofKind(BacklogKind::Embeddings)
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->findOrFail($id);

        // Recomputed on every poll rather than carried from the 202: the line
        // moves, and a position that does not is decoration.
        return QueuedCall::collect($request, $entry, $estimate->for($entry));
    }
}
