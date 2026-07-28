<?php

it('renders a flashed message as a toast', function () {
    $this->withSession([
        'laravel_flash_message' => [
            'message' => 'Profile saved.',
            'class' => 'success',
            'level' => 'success',
        ],
    ])
        ->get(route('auth.login'))
        ->assertOk()
        ->assertSee('Profile saved.', false)
        ->assertSee('aria-label="Dismiss"', false);
})->group('frontend', 'flash');

it('renders the Microsoft SSO error as a toast', function () {
    $this->withSession(['microsoft_entra_sso_error' => 'SSO sign-in failed.'])
        ->get(route('auth.login'))
        ->assertOk()
        ->assertSee('SSO sign-in failed.', false)
        ->assertSee('aria-label="Dismiss"', false);
})->group('frontend', 'flash');

it('renders no toast without flashed messages', function () {
    $this->get(route('auth.login'))
        ->assertOk()
        ->assertDontSee('aria-label="Dismiss"', false);
})->group('frontend', 'flash');
