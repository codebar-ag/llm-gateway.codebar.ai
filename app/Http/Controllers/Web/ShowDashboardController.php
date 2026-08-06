<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\BacklogStatus;
use App\Enums\UsageRange;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\UsageStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: what the gateway is doing now, and what it has been used
 * for over the chosen window.
 *
 * Split out of the listing on purpose. The status tiles used to sit on top of
 * a hundred log rows, which made the page answer "what happened to this one
 * request" and "how much is this thing being used" at once, and neither well.
 */
class ShowDashboardController
{
    public function __invoke(Request $request): Response
    {
        $range = UsageRange::fromRequest($request->query('range') === null ? null : (string) $request->query('range'));
        $usage = new UsageStatistics($range);

        /** @var Collection<string, string> $labels */
        $labels = ApiKeyLabel::query()->pluck('name', 'api_key_hash');

        $defaultGateway = Gateway::default();

        return Inertia::render('Dashboard', [
            'range' => [
                'value' => $range->value,
                'label' => $range->label(),
                'options' => self::rangeOptions($request, $range),
            ],

            // Unfiltered by the range, unlike everything below: how much work
            // is in the queue right now is a fact about this moment, and a
            // 24-hour window would hide an entry that has been stuck for two
            // days behind a tile reading zero.
            'statuses' => self::statusTiles(BacklogEntry::statusCounts()),
            'oldestQueued' => BacklogEntry::oldestQueuedAt()?->diffForHumans(),

            /*
             | Lazily resolved, all four: this page reloads itself on the same
             | fifteen-second timer as the listing and asks only for the live
             | tiles. Inertia drops a prop the request did not name before
             | resolving it, but only when the prop is a closure — left plain,
             | every refresh would pay for four GROUP BYs over the window to
             | redraw numbers that move by one.
             */
            'totals' => fn (): array => $usage->totals(),
            'perModel' => fn (): array => $usage->perModel(),
            'perCaller' => fn (): array => $usage->perCaller($labels),
            'volume' => fn (): array => $usage->overTime(),

            'summary' => [
                'workers' => Config::integer('gateway.backlog.workers'),
                'endpoint' => $defaultGateway?->endpoint(),
            ],
            'urls' => [
                'logs' => route('logs'),
            ],
        ]);
    }

    /**
     * The range picker, as links rather than as a form.
     *
     * The window is in the URL so a dashboard someone is looking at can be sent
     * to the next person and show them the same numbers.
     *
     * @return array<int, array{value: string, label: string, href: string, active: bool}>
     */
    private static function rangeOptions(Request $request, UsageRange $current): array
    {
        return array_map(fn (UsageRange $case): array => [
            'value' => $case->value,
            'label' => $case->shortLabel(),
            'href' => $request->fullUrlWithQuery(['range' => $case->value]),
            'active' => $case === $current,
        ], UsageRange::cases());
    }

    /**
     * Each tile links into the log listing already filtered to its status, so
     * "30 failed" is one click from which thirty.
     *
     * @param  Collection<array-key, int>  $counts
     * @return array<int, array{value: string, label: string, count: int, tone: string, href: string}>
     */
    private static function statusTiles(Collection $counts): array
    {
        return array_map(fn (BacklogStatus $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'count' => $counts[$case->value] ?? 0,
            'tone' => $case->tone(),
            'href' => route('logs', ['status' => $case->value]),
        ], BacklogStatus::cases());
    }
}
