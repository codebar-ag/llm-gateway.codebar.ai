<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BacklogKind;
use App\Services\Backlog;
use App\Services\QueueEstimate;
use App\Support\QueuedCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Queued, like every other call that costs the upstream real compute.
 *
 * This used to answer inline on the reasoning that no OpenAI client polls for
 * an embedding. That is true of a client embedding one sentence and wrong of
 * the traffic this gateway exists for: an indexing run fires tens of thousands
 * of batches, and answering them inline let every one of them reach the
 * backend at once — which is precisely the pile-up the backlog was built to
 * absorb. Now they queue, are run one at a time by the workers, retry on their
 * own budget, and are collected from `GET /v1/embeddings/{id}`.
 */
class CreateEmbeddingController
{
    public function __invoke(Request $request, Backlog $backlog, QueueEstimate $estimate): JsonResponse
    {
        // The body goes upstream exactly as it arrived.
        $entry = $backlog->add($request, $request->json()->all(), BacklogKind::Embeddings);

        // Estimated after the insert, so the entry counts itself and the
        // caller is told the line they are in rather than the one before it.
        return QueuedCall::accepted($request, $entry, $estimate->for($entry));
    }
}
