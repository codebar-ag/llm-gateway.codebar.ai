<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => config()->set('gateway.dashboard.token', ''));

it('issues a new password for an existing operator', function () {
    $user = User::factory()->create(['email' => 'ops@example.com']);
    $before = $user->password;

    $this->artisan('user:reset-password', ['--email' => 'ops@example.com'])->assertSuccessful();

    expect($user->fresh()->password)->not->toBe($before);
});

it('prints a password that actually signs in', function () {
    User::factory()->create(['email' => 'ops@example.com']);

    $password = generatedPassword('user:reset-password', ['--email' => 'ops@example.com']);

    $this->post(route('login.store'), ['email' => 'ops@example.com', 'password' => $password])
        ->assertRedirect(route('backlog'));

    $this->assertAuthenticated();
});

it('retires the old password', function () {
    User::factory()->create(['email' => 'ops@example.com']);

    $this->artisan('user:reset-password', ['--email' => 'ops@example.com'])->assertSuccessful();

    $this->post(route('login.store'), [
        'email' => 'ops@example.com',
        'password' => UserFactory::password(),
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('stores the new password hashed', function () {
    User::factory()->create(['email' => 'ops@example.com']);

    $password = generatedPassword('user:reset-password', ['--email' => 'ops@example.com']);
    $user = User::query()->where('email', 'ops@example.com')->sole();

    expect($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();
});

it('rotates the remember token so an old cookie is dead', function () {
    $user = User::factory()->create(['email' => 'ops@example.com', 'remember_token' => 'old-token']);

    $this->artisan('user:reset-password', ['--email' => 'ops@example.com'])->assertSuccessful();

    expect($user->fresh()->remember_token)->not->toBe('old-token');
});

it('signs out a browser that was already signed in', function () {
    $user = User::factory()->create(['email' => 'ops@example.com']);
    $hashBefore = $user->password;

    $this->artisan('user:reset-password', ['--email' => 'ops@example.com'])->assertSuccessful();

    /*
     | The stale hash is seeded rather than carried over from an earlier request:
     | each test request gets its own session, so there is no browser here whose
     | cookie survives the reset. This asserts the contract `auth.session`
     | actually enforces — a session opened against the old password is refused —
     | which is what makes rotating the password lock the old holder out at all.
     */
    $this->actingAs($user->fresh())
        ->withSession(['password_hash_web' => $hashBefore])
        ->get('/')
        ->assertRedirect(route('login'));
});

it('leaves a session opened against the current password alone', function () {
    $user = User::factory()->create();

    // The negative half: the guard must not sign out an operator who did nothing.
    $this->actingAs($user)
        ->withSession(['password_hash_web' => $user->password])
        ->get('/')
        ->assertOk();
});

it('fails on an address with no account', function () {
    $this->artisan('user:reset-password', ['--email' => 'nobody@example.com'])->assertFailed();
});

it('prompts for the address when it was not given', function () {
    User::factory()->create(['email' => 'ops@example.com']);

    $this->artisan('user:reset-password')
        ->expectsQuestion('Email', 'ops@example.com')
        ->assertSuccessful();
});

it('leaves the other operators alone', function () {
    $target = User::factory()->create(['email' => 'ops@example.com']);
    $other = User::factory()->create(['email' => 'other@example.com']);
    $othersPassword = $other->password;

    $this->artisan('user:reset-password', ['--email' => 'ops@example.com'])->assertSuccessful();

    expect($other->fresh()->password)->toBe($othersPassword)
        ->and($target->fresh()->password)->not->toBe($othersPassword);
});
