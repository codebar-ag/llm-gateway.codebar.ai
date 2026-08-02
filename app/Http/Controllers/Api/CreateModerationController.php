<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BacklogKind;
use App\Services\Backlog;
use App\Services\QueueEstimate;
use App\Support\QueuedCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Queued and collected the same way an embedding is — see `CreateEmbeddingController`. */
class CreateModerationController
{
    public function __invoke(Request $request, Backlog $backlog, QueueEstimate $estimate): JsonResponse
    {
        $entry = $backlog->add($request, $request->json()->all(), BacklogKind::Moderations);

        return QueuedCall::accepted($request, $entry, $estimate->for($entry));
    }
}
