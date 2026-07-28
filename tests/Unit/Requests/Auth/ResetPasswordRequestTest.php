<?php

use App\Models\User;
use App\Requests\Auth\ResetPasswordRequest;
use Illuminate\Support\Facades\Validator;

it('authorizes all users', function () {
    $request = new ResetPasswordRequest;

    expect($request->authorize())->toBeTrue();
})->group('unit', 'requests');

it('requires matching user email', function () {
    $request = new ResetPasswordRequest;
    $validator = Validator::make([
        'email' => 'missing@example.com',
        'password' => 'password1A',
        'password_confirmation' => 'password1A',
        'token' => 'tok',
    ], $request->rules());

    expect($validator->fails())->toBeTrue();
})->group('unit', 'requests');

it('accepts valid payload', function () {
    $user = User::factory()->create();

    $request = new ResetPasswordRequest;
    $validator = Validator::make([
        'email' => $user->email,
        'password' => 'Password1Aa',
        'password_confirmation' => 'Password1Aa',
        'token' => 'some-token',
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
})->group('unit', 'requests');
