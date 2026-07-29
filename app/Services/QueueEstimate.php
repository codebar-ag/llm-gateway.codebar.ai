<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Support\QueuePlace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * What a caller is told about the wait while their entry is queued.
 *
 * A projection, not a promise. It reads how long recent answers for the same
 * model actually took and divides the line by the number of workers — which
 * is right up until the backend swaps models, the case this whole app exists
 * to absorb and the one an average cannot see coming.
 */
class QueueEstimate
{
    /**
     * Only a queued entry has a place in line. One already running has no
     * position to report and no wait left to predict, and a finished one is
     * answered — all of them read as no queue rather than as position zero.
     */
    public function for(BacklogEntry $entry): ?QueuePlace
    {
        if ($entry->status !== BacklogStatus::Queued) {
            return null;
        }

        $ahead = BacklogEntry::queuedAhead($entry);
        $workers = max(1, Config::integer('gateway.backlog.workers'));

        /*
         | Running entries are counted even though they are no longer in line:
         | each one holds a worker slot, so it delays this entry exactly as an
         | entry ahead of it would. The `+ 1` is the entry itself, which is
         | what makes this the wait until its own answer rather than until it
         | merely starts.
         */
        $batches = (int) ceil(($ahead + BacklogEntry::runningCount() + 1) / $workers);
        $seconds = $batches * $this->secondsPerEntry($entry->model);

        return new QueuePlace(
            position: $ahead + 1,
            depth: BacklogEntry::queuedDepth(),
            workers: $workers,
            estimatedSeconds: $seconds,
            estimatedCompletionAt: now()->addSeconds($seconds)->getTimestamp(),
        );
    }

    /**
     * How long one answer for this model has been taking.
     *
     * Cached briefly because polling is unthrottled by design, and a median
     * over the last few entries does not move between two polls a second
     * apart. The position it is multiplied by is never cached: that one does
     * move, and a stale place in line is worse than none.
     */
    private function secondsPerEntry(?string $model): int
    {
        return Cache::remember(
            'backlog:estimate:'.($model ?? '*'),
            Config::integer('gateway.backlog.estimate.ttl'),
            function () use ($model): int {
                $sample = Config::integer('gateway.backlog.estimate.sample');

                /*
                 | A model nobody has asked yet borrows the global median, and
                 | a backlog with no answers at all falls back to the config.
                 | Reporting zero would read as "any moment now", which is the
                 | one thing an unmeasured queue cannot claim.
                 */
                return self::median(BacklogEntry::recentDurations($model, $sample))
                    ?? self::median(BacklogEntry::recentDurations(null, $sample))
                    ?? Config::integer('gateway.backlog.estimate.default_seconds');
            }
        );
    }

    /**
     * The median rather than the mean: one model swap that took four minutes
     * should not drag the estimate for every fast answer behind it.
     *
     * Computed in PHP because the database cannot be relied on for it —
     * Postgres has `percentile_cont` and the SQLite the tests run on does not.
     *
     * @param  Collection<int, int>  $durations
     */
    private static function median(Collection $durations): ?int
    {
        if ($durations->isEmpty()) {
            return null;
        }

        $sorted = $durations->sort()->values();
        $middle = intdiv($sorted->count(), 2);

        if ($sorted->count() % 2 === 1) {
            return $sorted->get($middle);
        }

        return (int) round((($sorted->get($middle - 1) ?? 0) + ($sorted->get($middle) ?? 0)) / 2);
    }
}
