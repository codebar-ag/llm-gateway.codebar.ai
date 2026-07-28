<?php

use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('is registered', function () {
    expect(app()->getProviders(AuthServiceProvider::class))->not->toBeEmpty();
})->group('unit', 'providers');

it('applies password defaults requiring mixed case', function () {
    $validator = Validator::make(
        ['password' => 'lowercase12345'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue();
})->group('unit', 'providers');
