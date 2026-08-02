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
        $invalid = self::invalidFilter($request);

        if ($invalid !== null) {
            return $invalid;
        }

        $status = self::text($request, 'status');
        $model = self::text($request, 'model');

        $entries = BacklogEntry::query()
            ->ownedBy(ForwardedHeaders::keyHash($request))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($model !== '', fn ($query) => $query->where('model', $model))
            ->latest()
            ->limit(self::limit($request))
            ->get();

        return response()->json([
            'object' => 'list',
            'data' => BacklogEntryResource::collection($entries)->resolve($request),
        ]);
    }

    /**
     * Refuse a filter this app cannot honour, rather than narrowing by it.
     *
     * A request *body* is deliberately never inspected — it is the upstream's to
     * judge. A filter is different: nothing downstream will ever see it, so a
     * typo would silently read as "no entries" instead of "no such status".
     *
     * Two ways one arrives unusable, and both have to answer 422 rather than
     * fall through to the builder:
     *
     * - a status this app has never heard of;
     * - a repeated parameter such as `?status[]=queued`, which is a malformed
     *   URL rather than a filter. That one used to slip past entirely: the
     *   check only looked at strings, and an array binding is flattened to its
     *   first element on the way to the query, so the caller was quietly
     *   filtered on something they had not asked for.
     */
    private static function invalidFilter(Request $request): ?JsonResponse
    {
        $status = self::text($request, 'status');

        if ($status === '' || BacklogStatus::tryFrom($status) instanceof BacklogStatus) {
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
     * A query parameter as the single value a filter has to be.
     *
     * `?status[]=queued` is a malformed URL rather than a filter, and reads as
     * none — the same rule `BacklogFilters::text()` applies to the dashboard's
     * query string, and what this endpoint's own test has always said it does.
     *
     * It did not, quite. The guard above only ever looked at strings, so an
     * array fell past it; and an array binding is flattened to its first
     * element on the way to the query, so the caller was quietly narrowed by a
     * value they had not asked to filter on. Reading it here makes the
     * behaviour the name.
     */
    private static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? trim((string) $value) : '';
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
