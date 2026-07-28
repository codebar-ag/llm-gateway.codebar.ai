<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;

test('database seeder leaves info@codebar.ch loggable with password', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Auth::attempt([
        'email' => 'info@codebar.ch',
        'password' => 'password',
    ]))->toBeTrue();
})->group('database', 'seeders');
