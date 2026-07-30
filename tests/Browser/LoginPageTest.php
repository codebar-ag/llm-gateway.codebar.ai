<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;

it('renders the sign in form', function () {
    visit('/login')
        ->assertSee('Sign in')
        ->assertSee('The dashboard shows every prompt and answer this gateway has handled.')
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertAttribute('input[name=email]', 'type', 'email')
        ->assertAttribute('input[name=email]', 'autocomplete', 'username')
        ->assertAttribute('input[name=password]', 'type', 'password')
        ->assertAttribute('input[name=password]', 'autocomplete', 'current-password')
        // No self-service recovery exists — only `php artisan user:reset-password`.
        ->assertDontSee('Forgot password')
        ->assertDontSee('Register');
});

it('lets a password manager recognize the credential fields', function () {
    // Regression test for the @codebar-ag/storybook bug where every non-password
    // Input was stamped data-1p-ignore/data-lpignore regardless of autocomplete,
    // which hid the email field from 1Password/LastPass entirely.
    visit('/login')
        ->assertAttributeMissing('input[name=email]', 'data-1p-ignore')
        ->assertAttributeMissing('input[name=email]', 'data-lpignore')
        ->assertAttributeMissing('input[name=password]', 'data-1p-ignore')
        ->assertAttributeMissing('input[name=password]', 'data-lpignore');
});

it('toggles the password field visibility', function () {
    visit('/login')
        ->assertAttribute('input[name=password]', 'type', 'password')
        ->assertAttribute('button[aria-label="Show password"]', 'aria-pressed', 'false')
        ->click('button[aria-label="Show password"]')
        ->assertAttribute('input[name=password]', 'type', 'text')
        ->assertAttribute('button[aria-label="Hide password"]', 'aria-pressed', 'true')
        ->click('button[aria-label="Hide password"]')
        ->assertAttribute('input[name=password]', 'type', 'password');
});

it('shows real validation messages instead of raw translation keys', function () {
    // Regression test for the missing lang/ directory: without it, Laravel
    // falls back to echoing translation keys like "validation.required" verbatim.
    visit('/login')
        ->click('button[type="submit"]')
        ->assertSee('The email field is required.')
        ->assertSee('The password field is required.')
        ->assertDontSee('validation.required');
});

it('rejects a wrong password and clears only the password field', function () {
    $user = User::factory()->create();

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'not-the-password')
        ->click('button[type="submit"]')
        ->assertSee('These credentials do not match our records.')
        ->assertValue('input[name=email]', $user->email)
        ->assertValue('input[name=password]', '');
});

it('signs an operator in and lands on the backlog', function () {
    $user = User::factory()->create();

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', UserFactory::password())
        ->click('button[type="submit"]')
        ->assertPathIs('/')
        ->assertSee('Backlog')
        ->assertSee('Sign out');

    $this->assertAuthenticatedAs($user);
});

it('signs an operator out and truly ends the session', function () {
    [$page] = signIn();

    $page->assertPathIs('/')
        ->click('Sign out')
        ->assertPathIs('/login');

    $this->assertGuest();

    // The session is dead server-side, not just the client-side nav state —
    // going back to a dashboard page must bounce to the login form again.
    $page->navigate('/')->assertPathIs('/login');
});
