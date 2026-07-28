<?php

use App\Models\User;

it('notice redirect if no user', function () {
    $this
        ->withoutMiddleware()
        ->get(route('auth.verification.notice'))
        ->assertRedirectToRoute('auth.login');
})->group('controllers', 'email', 'email-notice');

it('notice redirect if email verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('auth.verification.notice'))
        ->assertRedirect();
})->group('controllers', 'email', 'email-notice');

it('notice show email verification if email not verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('auth.verification.notice'))
        ->assertViewIs('auth.verify-email')
        ->assertStatus(200);
})->group('controllers', 'email', 'email-notice');
