<?php

use App\Models\User;

it('send redirect if no user', function () {
    $this
        ->withoutMiddleware()
        ->post(route('auth.verification.send'))
        ->assertRedirectToRoute('auth.login');
})->group('controllers', 'email');

it('send redirect if email verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('auth.verification.send'))
        ->assertRedirect();
})->group('controllers', 'email');

it('send email verification', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('auth.verification.send'))
        ->assertRedirect();
})->group('controllers', 'email');

it('rejects verification resend when honeypot field is filled', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->post(route('auth.verification.send'), [
            'my_name' => 'bot',
        ]);

    $response->assertOk();
    expect($response->getContent())->toBe('');
})->group('controllers', 'email');
