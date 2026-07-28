<?php

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

it('uses authorizes requests trait', function () {
    expect(class_uses_recursive(Controller::class))
        ->toContain(AuthorizesRequests::class);
})->group('unit', 'http');
