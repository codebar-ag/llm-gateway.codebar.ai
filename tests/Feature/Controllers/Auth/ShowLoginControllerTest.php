<?php

use App\Models\User;

it('unauthorized auth.login', function () {
    $this->get(route('auth.login'))
        ->assertOk();
})->group('auth', 'login');

it('authorized auth.login', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('auth.login'))
        ->assertRedirect();
})->group('auth', 'login');

it('passes providers from config to view', function () {
    config(['default.auth.providers' => ['microsoft']]);

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertViewHas('providers', collect(['microsoft']));
})->group('auth', 'login');

it('renders the Microsoft sign-in button when the provider is configured', function () {
    $this->get(route('auth.login'))
        ->assertOk()
        ->assertSee(route('microsoft-entra-sso.redirect', ['guard' => 'web']), false)
        ->assertSee(__('auth.sign-in-with-microsoft'), false);
})->group('auth', 'login');

it('hides the Microsoft sign-in button when no providers are configured', function () {
    config(['default.auth.providers' => []]);

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertDontSee(route('microsoft-entra-sso.redirect', ['guard' => 'web']), false)
        ->assertDontSee(__('auth.sign-in-with-microsoft'), false);
})->group('auth', 'login');
