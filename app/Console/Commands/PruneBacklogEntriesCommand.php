<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BacklogEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

/**
 * Empties the payload columns of entries old enough that nobody will read
 * them, and keeps everything else.
 *
 * The backlog is the log, but almost all of its weight is cargo: embedding
 * vectors a caller has long since collected, and base64 images inside OCR
 * requests. Measured against production, those two were 96% of the table.
 * The row itself — status, model, caller, usage, error, every timestamp —
 * is what the dashboard and its history are made of, so the row stays and
 * only `request` and `output` go. `headers` needs no pruning here: finishing
 * an entry already clears them.
 *
 * Only finished entries are touched. A queued entry's request is the call the
 * worker has yet to make, and an embeddings caller polls `output` for their
 * vectors — `finished_at` plus the window is what says both needs have passed.
 */
#[Signature('backlog:prune
    {--pretend : Report what would be pruned without writing}')]
#[Description('Empty the request and response bodies of entries finished longer ago than the retention window')]
class PruneBacklogEntriesCommand extends Command
{
    public function handle(): int
    {
        $days = Config::integer('gateway.backlog.prune_after_days');

        if ($days <= 0) {
            $this->components->info('Pruning is off — set BACKLOG_PRUNE_DAYS to a number of days to enable it.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        /*
         | `withTrashed`, because a soft deleted entry still holds its payload
         | and hides from every listing — precisely the row nobody will ever
         | read again. `whereNull('pruned_at')` keeps the run idempotent and
         | the count honest: a second pass reports zero rather than re-counting
         | rows it already emptied.
         */
        $matching = BacklogEntry::withTrashed()
            ->whereNull('pruned_at')
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff);

        $pruned = $this->option('pretend')
            ? $matching->count()
            : self::prune($matching);

        $this->components->info(sprintf(
            '%s %d %s finished before %s.',
            $this->option('pretend') ? 'Would prune' : 'Pruned',
            $pruned,
            $pruned === 1 ? 'entry' : 'entries',
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }

    /**
     * One UPDATE over the base query, not a model loop: hydrating a payload
     * in order to throw it away is the exact cost this command exists to end,
     * and `toBase()` also leaves `updated_at` alone — the value there records
     * the entry's own history, which pruning is housekeeping on, not part of.
     *
     * @param  Builder<BacklogEntry>  $matching
     */
    private static function prune(Builder $matching): int
    {
        return $matching->toBase()->update([
            'request' => null,
            'output' => null,
            'pruned_at' => now(),
        ]);
    }
}
