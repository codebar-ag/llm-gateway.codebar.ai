<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Moves timestamps written before the connection timezone was pinned onto the
 * instant they were meant to record.
 *
 * Eloquent hands Postgres a bare `Y-m-d H:i:s`, and Postgres resolves a bare
 * string against the session timezone. Until `connections.pgsql.timezone` was
 * set that was the server's default, so a wall clock the application meant as
 * Europe/Zurich was stored as the same wall clock somewhere else — an instant
 * off by the difference between the two zones, which the dashboard then showed
 * as an hour in the future.
 *
 * The correction is the inverse of that mistake, not a fixed offset: render
 * each stored instant in the zone it was misread as to recover the wall clock
 * the application actually wrote, then read that wall clock as Europe/Zurich.
 * Doing it through the zones rather than through a subtraction is what keeps
 * rows written either side of a daylight saving change correct.
 *
 * Rows written after the fix are already right, so this is a one-shot: running
 * it twice would shift correct rows away by the same amount.
 */
#[Signature('db:realign-timestamps
    {--from= : The timezone Postgres resolved bare timestamps against, e.g. UTC (default: the server timezone)}
    {--pretend : Report what would change without writing}')]
#[Description('Correct timestamps stored while Postgres resolved them against the wrong timezone')]
class RealignTimestampsCommand extends Command
{
    /**
     * Every timestamptz column in the schema, by table.
     *
     * All of them, not just `created_at`: `started_at` and `finished_at` are
     * what the entry page measures a queue wait and a duration from, and a
     * `deleted_at` left behind would sort a retired gateway wrongly.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'gateways' => ['created_at', 'updated_at', 'deleted_at'],
        'backlog_entries' => ['created_at', 'updated_at', 'started_at', 'finished_at', 'deleted_at'],
        'users' => ['created_at', 'updated_at'],
        'api_key_labels' => ['created_at', 'updated_at'],
    ];

    /** Microseconds are kept: the listing pages by `created_at`, and ties there page badly. */
    private const FORMAT = 'Y-m-d H:i:s.u';

    public function handle(): int
    {
        $target = Config::string('app.timezone');
        $source = $this->option('from') ?? $this->serverTimezone();

        if ($source === null) {
            $this->components->error('Could not read the server timezone. Pass the zone the timestamps were written against with --from, e.g. --from=UTC.');

            return self::FAILURE;
        }

        if (! $this->isTimezone($source)) {
            $this->components->error("{$source} is not a timezone PHP knows.");

            return self::FAILURE;
        }

        if ($source === $target) {
            $this->components->info("Nothing to realign: timestamps were already written against {$target}.");

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Misread as', $source);
        $this->components->twoColumnDetail('Meant as', $target);

        $shifted = 0;

        foreach (self::COLUMNS as $table => $columns) {
            $shifted += $this->realign($table, $columns, $source, $target);
        }

        if ($this->option('pretend')) {
            $this->components->warn("{$shifted} timestamps would move. Nothing was written.");

            return self::SUCCESS;
        }

        $this->components->info("{$shifted} timestamps realigned.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $columns
     */
    private function realign(string $table, array $columns, string $source, string $target): int
    {
        $shifted = 0;

        /*
         | The query builder rather than the models: soft deleted rows need the
         | same correction, and an Eloquent save would stamp `updated_at` with
         | the time of the repair over the value being repaired.
         */
        DB::table($table)
            ->chunkById(500, function (Collection $rows) use ($table, $columns, $source, &$shifted): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        $stored = $row->{$column};

                        // Both drivers hand back a datetime column as a string.
                        // Null is the ordinary case: a queued entry has no
                        // `finished_at`, a live gateway no `deleted_at`.
                        if (! is_string($stored)) {
                            continue;
                        }

                        /*
                         | Only half the correction is here. This recovers the
                         | wall clock the application wrote, and writing it back
                         | bare is what re-reads it as the application timezone
                         | — the session is pinned to that now, which is the
                         | whole reason new rows are already right.
                         */
                        $changes[$column] = CarbonImmutable::parse($stored)
                            ->setTimezone($source)
                            ->format(self::FORMAT);
                    }

                    if ($changes === []) {
                        continue;
                    }

                    $shifted += count($changes);

                    if ($this->option('pretend')) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });

        $this->components->twoColumnDetail($table, (string) $shifted);

        return $shifted;
    }

    /**
     * The timezone sessions inherited before `connections.pgsql.timezone` was
     * set — `reset_val` rather than `setting`, which now reports the zone this
     * very connection was pinned to.
     */
    private function serverTimezone(): ?string
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        /** @var object{reset_val: string}|null $setting */
        $setting = DB::selectOne("select reset_val from pg_settings where name = 'TimeZone'");

        return $setting?->reset_val;
    }

    private function isTimezone(string $zone): bool
    {
        try {
            new DateTimeZone($zone);
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
