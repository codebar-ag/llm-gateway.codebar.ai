<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BacklogStatus;
use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
use App\Support\ForwardedHeaders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListResponsesController
{
    private const MAX_LIMIT = 200;

    private const DEFAULT_LIMIT = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $unknownStatus = self::unknownStatus($request);

        if ($unknownStatus !== null) {
            return $unknownStatus;
        }

        $entries = BacklogEntry::query()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('model'), fn ($query, $model) => $query->where('model', $model))
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return response()->json([
            'object' => 'list',
            'data' => BacklogEntryResource::collection($entries)->resolve($request),
        ]);
    }

    /**
     * Refuse a status this app has never heard of, rather than filtering on it.
     *
     * A request *body* is deliberately never inspected — it is the upstream's to
     * judge. A filter is different: nothing downstream will ever see it, so a
     * typo would silently read as "no entries" instead of "no such status".
     */
    private static function unknownStatus(Request $request): ?JsonResponse
    {
        $status = $request->query('status');

        if (! is_string($status) || $status === '' || BacklogStatus::tryFrom($status) instanceof BacklogStatus) {
            return null;
        }

        return response()->json([
            'error' => [
                'message' => "Unknown status '{$status}'. Valid values are "
                    .implode(', ', array_column(BacklogStatus::cases(), 'value')).'.',
                'type' => 'invalid_request',
            ],
        ], 422);
    }

    /**
     * Clamped at both ends: a negative limit is a database error rather than
     * a query, and a non-numeric one would silently cast to an empty page.
     */
    private static function limit(Request $request): int
    {
        $requested = (int) $request->query('limit', self::DEFAULT_LIMIT);

        return max(1, min($requested, self::MAX_LIMIT));
    }
}
