<?php

use Spatie\Health\Facades\Health;

it('registers health checks', function () {
    expect(Health::registeredChecks()->isNotEmpty())->toBeTrue();
})->group('unit', 'providers');
