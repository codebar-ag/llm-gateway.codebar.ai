<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Tunnel;
use App\Support\BacklogFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class ShowBacklogController
{
    private const PER_PAGE = 100;

    public function __invoke(Request $request, Tunnel $tunnel): View
    {
        $filters = BacklogFilters::fromRequest($request);
        $matching = $filters->apply();
        $counts = BacklogEntry::statusCounts();

        return view('backlog', [
            'filters' => $filters,
            'statuses' => BacklogStatus::cases(),
            'models' => BacklogEntry::distinctModels(),
            'gateways' => Gateway::query()->orderBy('name')->get(),
            'counts' => $counts,
            'oldestQueued' => BacklogEntry::oldestQueuedAt(),
            'total' => self::total($filters, $matching, $counts),
            // Cursor rather than offset: this table only grows, and a deep
            // OFFSET has to read and discard every row it skips. `id` breaks
            // ties so a shared created_at cannot drop rows between pages.
            'entries' => $matching
                ->with('gateway')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->cursorPaginate(self::PER_PAGE)
                ->withQueryString(),
            'defaultGateway' => Gateway::default(),
            'workers' => Config::integer('gateway.backlog.workers'),
            'tunnel' => $tunnel->state(),
        ]);
    }

    /**
     * How many entries the current filter matches.
     *
     * Unfiltered that is the status counts this page already fetched, which is
     * worth summing rather than paying for a second unbounded COUNT over a
     * table that only grows — on a page that refreshes every fifteen seconds.
     *
     * @param  Builder<BacklogEntry>  $matching
     * @param  Collection<array-key, int>  $counts
     */
    private static function total(BacklogFilters $filters, Builder $matching, Collection $counts): int
    {
        return $filters->areEmpty()
            ? array_sum($counts->all())
            : (clone $matching)->count();
    }
}
