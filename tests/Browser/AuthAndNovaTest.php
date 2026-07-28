<?php

namespace Tests\Browser;

use App\Enums\RoleEnum;
use App\Models\User;
use Laravel\Dusk\Browser;

test('can access login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit(route('auth.login'))
            ->waitFor('input[name="email"]', 15)
            ->assertPresent('input[name="email"]')
            ->assertPresent('input[name="password"]')
            ->assertPresent('button[type="submit"]');
    });
})->group('browser', 'auth');

test('can log in and access dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit(route('auth.login'))
            ->waitFor('input[name="email"]', 15)
            ->type('input[name="email"]', $user->email)
            ->type('input[name="password"]', 'password')
            ->press(__('auth.sign-in'))
            ->waitForLocation('/')
            ->assertPathIs('/')
            ->assertSee(__('dashboard.title'), false);
    });
})->group('browser', 'auth');
