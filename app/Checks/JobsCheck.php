<?php

namespace App\Checks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class JobsCheck extends Check
{
    /** Samples taken when the jobs table is non-empty (stability vs drain detection). */
    public const int SAMPLE_COUNT = 5;

    /** Pause between samples; keeps total wait ~1s instead of ~90s per run. */
    public const int SAMPLE_INTERVAL_MILLISECONDS = 200;

    public function run(): Result
    {
        $count = $this->currentJobsTableCount();

        $result = Result::make();
        $result->shortSummary("jobs table count: {$count}");

        if ($count <= 0) {
            return $result->ok();
        }

        $newCounts = Collection::times(self::SAMPLE_COUNT, function () {
            Sleep::for(self::SAMPLE_INTERVAL_MILLISECONDS)->milliseconds();

            return $this->currentJobsTableCount();
        });

        $allMatch = $newCounts->every(fn (int $value) => $value === $count);

        $result->shortSummary('jobs table count: '.($allMatch ? 'stable' : 'changed'));

        if (! $allMatch) {
            return $result->ok();
        }

        return $result->failed();

    }

    protected function currentJobsTableCount(): int
    {
        return (int) DB::table('jobs')->count();
    }
}
