<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\BacklogStatus;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Support\BacklogFilters;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class ShowBacklogController
{
    private const PER_PAGE = 100;

    /** How much of an unnamed caller's hash the listing shows. */
    private const SHORT_HASH = 12;

    public function __invoke(Request $request): Response
    {
        $filters = BacklogFilters::fromRequest($request);
        $matching = $filters->apply();
        $counts = BacklogEntry::statusCounts();

        // Cursor rather than offset: this table only grows, and a deep OFFSET
        // has to read and discard every row it skips. `id` breaks ties so a
        // shared created_at cannot drop rows between pages.
        $entries = $matching
            ->with('gateway')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();

        $defaultGateway = Gateway::default();
        $labels = self::labels();

        return Inertia::render('Backlog', [
            'filters' => [
                'status' => $filters->status?->value,
                'model' => $filters->model,
                'search' => $filters->search,
                'gateway' => $filters->gateway,
                'caller' => $filters->caller,
                'areEmpty' => $filters->areEmpty(),
            ],
            'statuses' => self::statusTiles($request, $filters, $counts),
            'models' => BacklogEntry::distinctModels()
                ->map(fn (string $model): array => ['value' => $model, 'label' => $model])
                ->values()
                ->all(),
            'gateways' => Gateway::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Gateway $gateway): array => ['value' => $gateway->id, 'label' => $gateway->name])
                ->all(),
            'callers' => self::callerOptions(),
            'entries' => collect($entries->items())
                ->map(fn (BacklogEntry $entry): array => self::row($entry, $labels))
                ->all(),
            'oldestQueued' => BacklogEntry::oldestQueuedAt()?->diffForHumans(),
            'total' => self::total($filters, $matching, $counts),
            'pagination' => [
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
            ],
            'summary' => [
                'workers' => Config::integer('gateway.backlog.workers'),
                'endpoint' => $defaultGateway?->endpoint(),
            ],
            'urls' => [
                'backlog' => route('backlog'),
                'callers' => route('callers'),
            ],
        ]);
    }

    /**
     * @param  Collection<string, string>  $labels
     * @return array<string, mixed>
     */
    private static function row(BacklogEntry $entry, Collection $labels): array
    {
        // Explicit, because `gateway_id` is nullOnDelete and the relation really
        // can be null — whatever the static analyser infers from the belongsTo.
        $gateway = $entry->gateway;

        return [
            'id' => $entry->id,
            'model' => $entry->model ?? '—',
            'gateway' => $gateway === null ? '—' : $gateway->name,
            'caller' => self::caller($entry->api_key_hash, $labels),
            'status' => [
                'label' => $entry->status->label(),
                'tone' => $entry->status->tone(),
            ],
            'attempts' => $entry->attempts.'/'.$entry->max_attempts,
            'waited' => Duration::human($entry->waitedSeconds()),
            'waitedExact' => Duration::exact($entry->waitedSeconds()),
            'duration' => Duration::human($entry->durationSeconds()),
            'durationExact' => Duration::exact($entry->durationSeconds()),
            'created' => [
                'short' => $entry->created_at->diffForHumans(short: true),
                'exact' => $entry->created_at->toDateTimeString(),
            ],
            'href' => route('backlog.entry', $entry->id),
        ];
    }

    /**
     * How a caller is named in the listing.
     *
     * `value` is the hash rather than a label id, so a bookmarked filter URL
     * survives the label being renamed or deleted.
     *
     * @param  Collection<string, string>  $labels
     * @return array{value: string, label: string, hash: string|null, named: bool}
     */
    private static function caller(?string $hash, Collection $labels): array
    {
        if ($hash === null) {
            return [
                'value' => ApiKeyLabel::ANONYMOUS,
                'label' => 'anonymous',
                'hash' => null,
                'named' => false,
            ];
        }

        $name = $labels->get($hash);

        return [
            'value' => $hash,
            'label' => $name ?? substr($hash, 0, self::SHORT_HASH),
            'hash' => $hash,
            'named' => $name !== null,
        ];
    }

    /**
     * Every caller the backlog has seen, for the filter.
     *
     * Named callers sort first and alphabetically; the rest follow by how
     * recently they were active, so the list stays usable once it is mostly
     * unnamed hashes.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function callerOptions(): array
    {
        $labels = self::labels();

        $callers = BacklogEntry::callerActivity()
            ->sortByDesc(fn (array $caller): string => $caller['last_seen']->toDateTimeString())
            ->map(fn (array $caller): array => self::caller($caller['api_key_hash'], $labels));

        return $callers->where('named', true)->sortBy('label')
            ->concat($callers->where('named', false))
            ->map(fn (array $caller): array => ['value' => $caller['value'], 'label' => $caller['label']])
            ->values()
            ->all();
    }

    /**
     * The clickable status tiles, each toggling its own filter.
     *
     * `cursor`, not `page`: the listing is cursor paginated, and carrying a
     * cursor taken from one filter's result set into another silently drops
     * every row that sat before it.
     *
     * @param  Collection<array-key, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private static function statusTiles(Request $request, BacklogFilters $filters, Collection $counts): array
    {
        return array_map(function (BacklogStatus $case) use ($request, $filters, $counts): array {
            $active = $filters->status === $case;

            return [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value] ?? 0,
                'active' => $active,
                'href' => $request->fullUrlWithQuery([
                    'status' => $active ? null : $case->value,
                    'cursor' => null,
                ]),
            ];
        }, BacklogStatus::cases());
    }

    /**
     * Caller names by hash. `pluck()` widens to `mixed`, and both callers of
     * this need the narrow type to say what they mean.
     *
     * @return Collection<string, string>
     */
    private static function labels(): Collection
    {
        /** @var Collection<string, string> $labels */
        $labels = ApiKeyLabel::query()->pluck('name', 'api_key_hash');

        return $labels;
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
