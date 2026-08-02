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
        $batches = (int) ceil(($ahead + $this->runningCount() + 1) / $workers);
        $seconds = $batches * $this->secondsPerEntry($entry->model);

        return new QueuePlace(
            position: $ahead + 1,
            depth: $this->queuedDepth(),
            workers: $workers,
            estimatedSeconds: $seconds,
            estimatedCompletionAt: now()->addSeconds($seconds)->getTimestamp(),
        );
    }

    /**
     * How many entries a worker is busy with, and so cannot start another for.
     *
     * The same answer for everyone. `GET /v1/responses/{id}` is documented as
     * something to poll in a loop and is deliberately unthrottled, so a hundred
     * callers waiting on the same backlog were each paying for their own COUNT
     * of a number none of them can influence and all of them share. Cached for
     * the same window the measurement uses, for the same reason: the running
     * set does not turn over between two polls a second apart, and being at
     * most one ttl behind moves the estimate by a batch at worst.
     *
     * Kept out of the `backlog:estimate:` namespace on purpose — that prefix is
     * completed by a caller-supplied model name, and a model called `running`
     * would otherwise collide with this key and be quoted a wait in entries.
     */
    private function runningCount(): int
    {
        return Cache::remember(
            'backlog:running-count',
            Config::integer('gateway.backlog.estimate.ttl'),
            fn (): int => BacklogEntry::runningCount(),
        );
    }

    /**
     * How many entries are waiting, across every caller and gateway.
     *
     * Global like the running count, and cached with it. Depth is context
     * rather than the caller's own standing — it says how busy the backlog is,
     * not where they are in it — so a number a few seconds old still tells them
     * what they read it for. The position beside it is what has to be exact,
     * and that one is never cached.
     */
    private function queuedDepth(): int
    {
        return Cache::remember(
            'backlog:queued-depth',
            Config::integer('gateway.backlog.estimate.ttl'),
            fn (): int => BacklogEntry::queuedDepth(),
        );
    }

    /**
     * How long one answer for this model has been taking.
     *
     * Cached briefly because polling is unthrottled by design, and a median
     * over the last few entries does not move between two polls a second
     * apart. The position it is multiplied by is never cached: that one does
     * move, and a stale place in line is worse than none.
     *
     * That split is what decides all three caches. Everything a poll asks that
     * is the same for every caller — this measurement, the running count, the
     * depth — is counted once per ttl and shared; only `queuedAhead()`, the one
     * number that is about this entry and changes as the line moves, is read
     * live on every poll.
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
