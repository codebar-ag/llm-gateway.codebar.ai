<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\BacklogEntry;
use App\Support\ForwardedHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteResponseController
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        BacklogEntry::query()
            ->queueable()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->findOrFail($id)
            ->delete();

        return response()->json(['id' => $id, 'object' => 'response.deleted', 'deleted' => true]);
    }
}
