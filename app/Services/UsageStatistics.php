<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BacklogStatus;
use App\Enums\UsageRange;
use App\Models\BacklogEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * What the gateway has actually been asked to do, measured.
 *
 * Read straight out of `backlog_entries` rather than from a rollup table: every
 * request this app has ever accepted is already a row there, with the usage the
 * upstream reported and the two timestamps a duration comes from. A summary
 * table would be a second copy to keep honest for numbers a GROUP BY answers.
 *
 * Both drivers are addressed explicitly below — Postgres in production, SQLite
 * under the test suite. The alternative, pulling the rows into PHP to add them
 * up, reads a month of traffic into memory on a page that refreshes itself, so
 * the branch is worth its keep.
 */
class UsageStatistics
{
    /** How many rows a breakdown table shows before it stops being a summary. */
    private const BREAKDOWN_LIMIT = 15;

    /** How many bars the volume chart draws at most, newest kept. */
    private const BUCKET_LIMIT = 180;

    public function __construct(private readonly UsageRange $range) {}

    /**
     * The headline counters.
     *
     * @return array{
     *     requests: int,
     *     completed: int,
     *     failed: int,
     *     queued: int,
     *     running: int,
     *     cancelled: int,
     *     failureRate: float|null,
     *     inputTokens: int,
     *     outputTokens: int,
     *     totalTokens: int,
     *     avgDuration: int|null,
     *     maxDuration: int|null,
     *     avgWait: int|null
     * }
     */
    public function totals(): array
    {
        $counts = $this->scope()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (stdClass $row): array => [$this->text($row->status) => $this->number($row->total)]);

        $requests = $this->number($counts->sum());
        $failed = $counts->get(BacklogStatus::Failed->value, 0);
        $completed = $counts->get(BacklogStatus::Completed->value, 0);

        $tokens = $this->scope()
            ->selectRaw('coalesce(sum('.$this->tokens('input_tokens').'), 0) as input')
            ->selectRaw('coalesce(sum('.$this->tokens('output_tokens').'), 0) as output')
            ->first();

        $input = $this->number($tokens->input ?? null);
        $output = $this->number($tokens->output ?? null);

        return [
            'requests' => $requests,
            'completed' => $completed,
            'failed' => $failed,
            'queued' => $counts->get(BacklogStatus::Queued->value, 0),
            'running' => $counts->get(BacklogStatus::Running->value, 0),
            'cancelled' => $counts->get(BacklogStatus::Cancelled->value, 0),

            /*
             | Out of the entries that reached an upstream at all. A queue full
             | of work not yet started is not a failing gateway, and dividing by
             | every row would quietly improve the rate the busier it gets.
             */
            'failureRate' => $completed + $failed === 0 ? null : round($failed / ($completed + $failed) * 100, 1),

            'inputTokens' => $input,
            'outputTokens' => $output,
            // Summed from the two halves rather than read from the upstream's
            // own `total_tokens`: an embedding reports no completion tokens, and
            // a provider that omits the total would read as no spend at all.
            'totalTokens' => $input + $output,
            ...$this->timings(),
        ];
    }

    /**
     * Traffic per model, busiest first.
     *
     * @return array<int, array{model: string, requests: int, inputTokens: int, outputTokens: int, totalTokens: int, avgDuration: int|null}>
     */
    public function perModel(): array
    {
        return $this->scope()
            ->select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum('.$this->tokens('input_tokens').'), 0) as input')
            ->selectRaw('coalesce(sum('.$this->tokens('output_tokens').'), 0) as output')
            ->selectRaw('avg('.$this->seconds('started_at', 'finished_at').') as duration')
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('requests')
            ->limit(self::BREAKDOWN_LIMIT)
            ->get()
            ->map(fn (stdClass $row): array => [
                'model' => $this->text($row->model),
                'requests' => $this->number($row->requests),
                'inputTokens' => $this->number($row->input),
                'outputTokens' => $this->number($row->output),
                'totalTokens' => $this->number($row->input) + $this->number($row->output),
                'avgDuration' => $this->rounded($row->duration),
            ])
            ->all();
    }

    /**
     * Traffic per caller, busiest first.
     *
     * @param  Collection<string, string>  $labels  caller name by key hash
     * @return array<int, array{caller: string, named: bool, requests: int, totalTokens: int}>
     */
    public function perCaller(Collection $labels): array
    {
        return $this->scope()
            ->select('api_key_hash')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum('.$this->tokens('input_tokens').'), 0) as input')
            ->selectRaw('coalesce(sum('.$this->tokens('output_tokens').'), 0) as output')
            ->groupBy('api_key_hash')
            ->orderByDesc('requests')
            ->limit(self::BREAKDOWN_LIMIT)
            ->get()
            ->map(function (stdClass $row) use ($labels): array {
                $hash = $row->api_key_hash === null ? null : $this->text($row->api_key_hash);
                $name = $hash === null ? null : $labels->get($hash);

                return [
                    // An unnamed key is shown by the head of its hash, the same
                    // way the listing shows it, so both pages name a caller the
                    // same before anyone has labelled them.
                    'caller' => $name ?? ($hash === null ? 'anonymous' : substr($hash, 0, 12)),
                    'named' => $name !== null,
                    'requests' => $this->number($row->requests),
                    'totalTokens' => $this->number($row->input) + $this->number($row->output),
                ];
            })
            ->all();
    }

    /**
     * Requests and tokens per bucket, oldest first — the volume chart.
     *
     * @return array<int, array{bucket: string, requests: int, totalTokens: int}>
     */
    public function overTime(): array
    {
        $bucket = $this->bucket();

        return $this->scope()
            ->selectRaw($bucket.' as bucket')
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum('.$this->tokens('input_tokens').'), 0) as input')
            ->selectRaw('coalesce(sum('.$this->tokens('output_tokens').'), 0) as output')
            ->groupBy(DB::raw($bucket))
            // Newest first so the cap falls off the far end of history, then
            // reversed: a chart reads left to right in time.
            ->orderByDesc(DB::raw($bucket))
            ->limit(self::BUCKET_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (stdClass $row): array => [
                'bucket' => $this->text($row->bucket),
                'requests' => $this->number($row->requests),
                'totalTokens' => $this->number($row->input) + $this->number($row->output),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{avgDuration: int|null, maxDuration: int|null, avgWait: int|null}
     */
    private function timings(): array
    {
        $answered = $this->scope()
            ->selectRaw('avg('.$this->seconds('started_at', 'finished_at').') as average')
            ->selectRaw('max('.$this->seconds('started_at', 'finished_at').') as slowest')
            ->where('status', BacklogStatus::Completed->value)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->first();

        // Every started entry, not only the completed ones: how long work waits
        // to be picked up is a property of the queue, and a failure waited too.
        $waited = $this->scope()
            ->selectRaw('avg('.$this->seconds('created_at', 'started_at').') as average')
            ->whereNotNull('started_at')
            ->first();

        return [
            'avgDuration' => $this->rounded($answered->average ?? null),
            'maxDuration' => $this->rounded($answered->slowest ?? null),
            'avgWait' => $this->rounded($waited->average ?? null),
        ];
    }

    /**
     * The rows every measurement here is taken over.
     *
     * `toBase()` keeps the soft-delete scope — global scopes are applied on the
     * way down — while handing back plain rows, which is what an aggregate
     * select produces: a `count(*)` is not a BacklogEntry.
     */
    private function scope(): QueryBuilder
    {
        $since = $this->range->since();

        return BacklogEntry::query()
            ->when($since, fn (Builder $query) => $query->where('created_at', '>=', $since))
            ->toBase();
    }

    /**
     * One token count out of the usage the upstream reported.
     *
     * Postgres hands JSON back as text and will not sum it without a cast;
     * SQLite has no cast to apply and needs none. Interpolated rather than
     * bound, because a binding inside a `select` binds ahead of the `where`
     * clauses added after it — and the key is a constant of this class.
     *
     * @param  literal-string  $key
     * @return literal-string
     */
    private function tokens(string $key): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "(usage->>'{$key}')::bigint",
            default => "json_extract(usage, '$.{$key}')",
        };
    }

    /**
     * Seconds between two timestamps, null when either is missing.
     *
     * Null rather than zero is what makes the averages honest: `avg()` skips a
     * null, so an entry that never started does not pull the mean down as if it
     * had been answered instantly.
     *
     * @param  literal-string  $from
     * @param  literal-string  $to
     * @return literal-string
     */
    private function seconds(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "extract(epoch from ({$to} - {$from}))",
            default => "(julianday({$to}) - julianday({$from})) * 86400",
        };
    }

    /**
     * The chart's grouping key, as text.
     *
     * Both expressions read the timestamp in the connection's timezone, which
     * is pinned to the application's — so a bar is a local day, the same day
     * the listing prints its timestamps in.
     *
     * @return literal-string
     */
    private function bucket(): string
    {
        $byHour = $this->range->bucketsByHour();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => $byHour
                ? "to_char(created_at, 'YYYY-MM-DD HH24:00')"
                : "to_char(created_at, 'YYYY-MM-DD')",
            default => $byHour
                ? "strftime('%Y-%m-%d %H:00', created_at)"
                : "strftime('%Y-%m-%d', created_at)",
        };
    }

    /**
     * A count out of an aggregate row.
     *
     * Aggregates arrive as whatever the driver felt like: `count(*)` is an int
     * under SQLite and a string under Postgres, and a `sum()` over no rows is
     * null before the coalesce ever runs.
     */
    private function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** A grouping key out of an aggregate row — a status, a model, a bucket. */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Seconds come back as a float from `avg()`, and as a string from some drivers. */
    private function rounded(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
