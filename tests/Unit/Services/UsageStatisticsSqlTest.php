<?php

declare(strict_types=1);

use App\Enums\UsageRange;
use App\Services\UsageStatistics;
use Illuminate\Support\Facades\DB;

/*
 | The suite runs on sqlite, so the Postgres arms of the driver matches never
 | execute on their own. The strings still have to be right — they are the SQL
 | production runs — so the driver is pinned and the expressions read back
 | directly. Reflection, because the builders are private on purpose: the
 | service's public surface hands out numbers, not SQL.
 */

it('buckets the volume by hour and by day in postgres', function () {
    DB::shouldReceive('connection->getDriverName')->andReturn('pgsql');

    $bucket = new ReflectionMethod(UsageStatistics::class, 'bucket');

    expect($bucket->invoke(new UsageStatistics(UsageRange::Day)))
        ->toBe("to_char(created_at, 'YYYY-MM-DD HH24:00')")
        ->and($bucket->invoke(new UsageStatistics(UsageRange::All)))
        ->toBe("to_char(created_at, 'YYYY-MM-DD')");
});
