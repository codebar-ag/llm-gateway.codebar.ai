<?php

use App\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Validator;

it('validates required email and password', function () {
    $request = new LoginRequest;
    $rules = $request->rules();

    $validator = Validator::make([], $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
})->group('unit', 'requests');

it('accepts valid credentials shape', function () {
    $request = new LoginRequest;
    $validator = Validator::make([
        'email' => 'user@example.com',
        'password' => 'secret',
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
})->group('unit', 'requests');
