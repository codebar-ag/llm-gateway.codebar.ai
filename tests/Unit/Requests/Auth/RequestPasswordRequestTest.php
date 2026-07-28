<?php

use App\Requests\Auth\RequestPasswordRequest;
use Illuminate\Support\Facades\Validator;

it('authorizes all users', function () {
    $request = new RequestPasswordRequest;

    expect($request->authorize())->toBeTrue();
})->group('unit', 'requests');

it('validates email', function () {
    $request = new RequestPasswordRequest;
    $validator = Validator::make([], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue();
})->group('unit', 'requests');

it('accepts valid email', function () {
    $request = new RequestPasswordRequest;
    $validator = Validator::make([
        'email' => 'reset@example.com',
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
})->group('unit', 'requests');
