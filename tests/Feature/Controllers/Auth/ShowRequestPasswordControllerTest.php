<?php

use App\Models\User;

test('unauthorized auth.request-password.index', function () {
    $this->get(route('auth.request-password'))
        ->assertOk();
})->group('auth', 'request-password');

test('authorized auth.request-password.index', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('auth.request-password'))
        ->assertRedirect();
})->group('auth', 'request-password');
