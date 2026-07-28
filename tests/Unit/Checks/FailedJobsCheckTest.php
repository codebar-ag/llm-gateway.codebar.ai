<?php

use App\Checks\FailedJobsCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Health\Enums\Status;

it('returns ok when there are no failed jobs', function () {
    DB::table('failed_jobs')->delete();

    $result = (new FailedJobsCheck)->run();

    expect($result->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('returns failed when failed_jobs has rows', function () {
    DB::table('failed_jobs')->delete();
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([]),
        'exception' => 'test',
    ]);

    $result = (new FailedJobsCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue();

    DB::table('failed_jobs')->delete();
})->group('unit', 'checks');
