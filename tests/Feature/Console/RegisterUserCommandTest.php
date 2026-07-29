<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers an operator from options', function () {
    $this->artisan('user:register', [
        '--name' => 'Ops',
        '--email' => 'ops@example.com',
        '--password' => 'a-long-enough-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ops@example.com')->sole();

    expect($user->name)->toBe('Ops');
});

it('stores the password hashed', function () {
    $this->artisan('user:register', [
        '--name' => 'Ops',
        '--email' => 'ops@example.com',
        '--password' => 'a-long-enough-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ops@example.com')->sole();

    expect($user->password)->not->toBe('a-long-enough-password')
        ->and(Hash::check('a-long-enough-password', $user->password))->toBeTrue();
});

it('refuses a duplicate address', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:register', [
        '--name' => 'Second',
        '--email' => 'taken@example.com',
        '--password' => 'a-long-enough-password',
    ])->assertFailed();

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

it('refuses a password short enough to guess', function () {
    $this->artisan('user:register', [
        '--name' => 'Ops',
        '--email' => 'ops@example.com',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::query()->where('email', 'ops@example.com')->exists())->toBeFalse();
});

it('refuses an address that is not one', function () {
    $this->artisan('user:register', [
        '--name' => 'Ops',
        '--email' => 'not-an-address',
        '--password' => 'a-long-enough-password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('prompts for what it was not given', function () {
    $this->artisan('user:register')
        ->expectsQuestion('Name', 'Prompted')
        ->expectsQuestion('Email', 'prompted@example.com')
        ->expectsQuestion('Password', 'a-long-enough-password')
        ->assertSuccessful();

    expect(User::query()->where('email', 'prompted@example.com')->sole()->name)->toBe('Prompted');
});

it('registers an account that can then sign in', function () {
    config()->set('gateway.dashboard.token', '');

    $this->artisan('user:register', [
        '--name' => 'Ops',
        '--email' => 'ops@example.com',
        '--password' => 'a-long-enough-password',
    ])->assertSuccessful();

    // The command is the only way an account is created, so this is the
    // assertion that the two halves actually fit together.
    $this->post(route('login.store'), [
        'email' => 'ops@example.com',
        'password' => 'a-long-enough-password',
    ])->assertRedirect(route('backlog'));

    $this->assertAuthenticated();
});
