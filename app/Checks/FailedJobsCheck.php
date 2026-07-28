<?php

namespace App\Checks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class FailedJobsCheck extends Check
{
    public function run(): Result
    {
        $count = DB::table('failed_jobs')->count();

        $result = Result::make();
        $result->shortSummary("failed jobs table count: {$count}");

        if ($count > 0) {
            return $result->failed();
        }

        return $result->ok();
    }
}
