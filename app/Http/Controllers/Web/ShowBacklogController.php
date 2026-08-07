<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Support\BacklogFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ShowBacklogController
{
    private const PER_PAGE = 100;

    /** How much of an unnamed caller's hash the listing shows. */
    private const SHORT_HASH = 12;

    /**
     * Exactly what a row renders, and nothing else.
     *
     * Named explicitly because `request` and `output` are the whole point of
     * leaving them out: an OCR call carries its image into the request as
     * base64, so a hundred of them hydrated together is hundreds of megabytes
     * read out of Postgres, decoded into PHP arrays by the `array` cast, and
     * then thrown away unread — the listing shows an id, a status, a model and
     * a time. A page that used to fit in memory stops fitting the day someone
     * points a vision model at it, and a fatal there is a blank 500 rather than
     * an error anyone can act on.
     *
     * `created_at` and `id` also carry the cursor, and `gateway_id` the
     * eager-loaded relation, so all three have to be here regardless.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'id', 'status', 'kind', 'model', 'gateway_id', 'api_key_hash', 'created_at',
    ];

    public function __invoke(Request $request): Response
    {
        $filters = BacklogFilters::fromRequest($request);
        $matching = $filters->apply();
        $counts = BacklogEntry::statusCounts();

        // Cursor rather than offset: this table only grows, and a deep OFFSET
        // has to read and discard every row it skips. `id` breaks ties so a
        // shared created_at cannot drop rows between pages.
        $entries = $matching
            ->select(self::COLUMNS)
            ->with('gateway')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();

        $labels = self::labels();

        return Inertia::render('Logs', [
            'filters' => [
                'status' => $filters->status?->value,
                'kind' => $filters->kind?->value,
                'model' => $filters->model,
                'search' => $filters->search,
                'gateway' => $filters->gateway,
                'caller' => $filters->caller,
                'areEmpty' => $filters->areEmpty(),
            ],
            'statuses' => self::statusOptions(),

            /*
             | Lazily resolved, all three: the page reloads itself every fifteen
             | seconds and asks only for the listing and its counters, and
             | Inertia drops a prop the request did not name *before* resolving
             | it — but only when the prop is a closure. Left as plain values
             | these ran a DISTINCT scan, a full gateway read and a GROUP BY
             | over the whole backlog on every refresh, to fill three dropdowns
             | that only change when a model, gateway or caller is seen for the
             | very first time. Not cached: a full page load must still show
             | that first sighting, and a TTL is a staleness window bought for
             | work Inertia is already skipping.
             */
            // Not lazy, unlike the three below it: the kinds are the enum's
            // own cases, so there is no query to skip.
            'kinds' => self::kindOptions(),
            'models' => fn (): array => self::modelOptions(),
            'gateways' => fn (): array => self::gatewayOptions(),
            'callers' => fn (): array => self::callerOptions($labels),

            'entries' => collect($entries->items())
                ->map(fn (BacklogEntry $entry): array => self::row($entry, $labels))
                ->all(),
            'total' => self::total($filters, $matching, $counts),
            'pagination' => [
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
            ],
            // `filters`, `pagination` and `urls` stay plain: they are reads of
            // values this method already holds, so wrapping them would buy a
            // closure allocation and nothing else.
            'urls' => [
                'logs' => route('logs'),
            ],
        ]);
    }

    /**
     * Every endpoint an entry can have been logged for, for the filter.
     *
     * Taken from the enum rather than from what the table happens to hold, so
     * "no embeddings today" is a listing that comes back empty rather than a
     * filter that quietly disappears.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function kindOptions(): array
    {
        return array_map(
            fn (BacklogKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            BacklogKind::cases(),
        );
    }

    /**
     * Every model the backlog has seen, for the filter.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function modelOptions(): array
    {
        return BacklogEntry::distinctModels()
            ->map(fn (string $model): array => ['value' => $model, 'label' => $model])
            ->values()
            ->all();
    }

    /**
     * Every gateway, for the filter.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function gatewayOptions(): array
    {
        return Gateway::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Gateway $gateway): array => ['value' => $gateway->id, 'label' => $gateway->name])
            ->all();
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
            // Only when it is not the chat completion everything else on this
            // page is: naming the norm on every row would be noise, and an
            // embedding row that looks like a completion is the confusion.
            'kind' => $entry->kind->isChat() ? null : $entry->kind->label(),
            'gateway' => $gateway === null ? '—' : $gateway->name,
            'caller' => self::caller($entry->api_key_hash, $labels),
            'status' => [
                'label' => $entry->status->label(),
                'tone' => $entry->status->tone(),
            ],
            // Attempts, waited and duration are deliberately absent: they are
            // per-entry detail, and carrying them here widened every row on a
            // listing that reloads itself every fifteen seconds. The entry page
            // has all three.
            /*
             | The clock time leads and the rounded age follows it. "25m" alone
             | answers "how long ago" but not "when", and on a listing someone
             | reads next to an upstream's own logs the second question is the
             | one being asked. Down to the second, because that is the
             | resolution the upstream's own log lines are matched against.
             */
            'created' => [
                'stamp' => $entry->created_at->format('d.m.Y H:i:s'),
                'short' => $entry->created_at->diffForHumans(short: true),
                'exact' => $entry->created_at->toDateTimeString(),
            ],
            'href' => route('logs.entry', $entry->id),
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
     * The labels are passed in rather than looked up: the listing rows need the
     * same map, and fetching it here as well ran `api_key_labels` twice per
     * request for one identical answer.
     *
     * @param  Collection<string, string>  $labels
     * @return array<int, array{value: string, label: string}>
     */
    private static function callerOptions(Collection $labels): array
    {
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
     * Every status an entry can be in, for the filter.
     *
     * From the enum rather than from the counts, for the same reason the
     * endpoints are: a day with nothing cancelled should offer the filter and
     * come back empty, not drop the option.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function statusOptions(): array
    {
        return array_map(
            fn (BacklogStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
            BacklogStatus::cases(),
        );
    }

    /**
     * Caller names by hash, fetched once and handed to everything that needs
     * it. `pluck()` widens to `mixed`, and the narrow type is what lets the
     * listing rows and the caller filter share the one map.
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
