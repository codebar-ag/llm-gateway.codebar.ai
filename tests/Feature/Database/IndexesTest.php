<?php

use Illuminate\Support\Facades\Schema;

test('notifications table has a read_at index', function () {
    expect(Schema::hasIndex('notifications', ['read_at']))->toBeTrue();
})->group('database', 'indexes');

test('activity log table has a created_at index', function () {
    $connection = config('activitylog.database_connection');
    $table = config('activitylog.table_name');

    expect(Schema::connection($connection)->hasIndex($table, ['created_at']))->toBeTrue();
})->group('database', 'indexes');
