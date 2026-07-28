<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Honeypot\ProtectAgainstSpam;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;

test('authorized auth.login.store', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole(RoleEnum::USER);

    $this
        ->withoutMiddleware()
        ->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirectToRoute('start.index');

    assertAuthenticated();
})->group('auth', 'login');

test('authorized wrong password auth.login.store', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this
        ->withoutMiddleware()
        ->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors();

    assertGuest();
})->group('auth', 'login');

test('seeded info@codebar.ch reaches dashboard after login with default route middleware', function () {
    $this->seed(DatabaseSeeder::class);

    $this->withoutMiddleware([
        ProtectAgainstSpam::class,
        ThrottleRequests::class,
    ]);

    $this->get(route('auth.login'));

    $this->post(route('auth.login.store'), [
        'email' => 'info@codebar.ch',
        'password' => 'password',
    ])
        ->assertRedirectToRoute('start.index');

    assertAuthenticated();

    $this->get(route('start.index'))
        ->assertOk();
})->group('auth', 'login');
