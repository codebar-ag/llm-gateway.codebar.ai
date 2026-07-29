<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
use App\Support\ForwardedHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowResponseController
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $entry = BacklogEntry::query()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->findOrFail($id);

        return (new BacklogEntryResource($entry))->response($request);
    }
}
