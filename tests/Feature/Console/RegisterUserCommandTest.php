<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\GeneratedPassword;
use Illuminate\Support\Facades\Hash;

/**
 * Runs a command and returns the password it printed.
 *
 * `Artisan::call()` rather than `$this->artisan()`: the latter returns a
 * PendingCommand with its own mocked output, so `Artisan::output()` comes back
 * empty and there is nothing to read the generated password out of.
 *
 * @param  array<string, string>  $arguments
 */
function generatedPassword(string $command, array $arguments): string
{
    Artisan::call($command, $arguments);

    $output = Artisan::output();

    expect($output)->toMatch('/[A-Za-z0-9]{'.GeneratedPassword::LENGTH.'}/');

    preg_match('/([A-Za-z0-9]{'.GeneratedPassword::LENGTH.'})/', $output, $matches);

    return $matches[1];
}

it('registers an operator', function () {
    $this->artisan('user:register', ['--name' => 'Ops', '--email' => 'ops@example.com'])
        ->assertSuccessful();

    expect(User::query()->where('email', 'ops@example.com')->sole()->name)->toBe('Ops');
});

it('generates the password instead of taking one', function () {
    $this->artisan('user:register', ['--name' => 'Ops', '--email' => 'ops@example.com'])
        ->assertSuccessful();

    $user = User::query()->where('email', 'ops@example.com')->sole();

    // Nothing about the account is guessable from what was typed.
    expect(Hash::check('ops@example.com', $user->password))->toBeFalse()
        ->and(Hash::check('Ops', $user->password))->toBeFalse()
        ->and($user->password)->toStartWith('$2y$');
});

it('shows the password once so it can be handed over', function () {
    $this->artisan('user:register', ['--name' => 'Ops', '--email' => 'ops@example.com'])
        ->assertSuccessful()
        ->expectsOutputToContain('Password');
});

it('stores the shown password hashed, and it works', function () {
    config()->set('gateway.dashboard.token', '');

    $password = generatedPassword('user:register', ['--name' => 'Ops', '--email' => 'ops@example.com']);
    $user = User::query()->where('email', 'ops@example.com')->sole();

    expect(Hash::check($password, $user->password))->toBeTrue();

    // The end that matters: the string it printed is the string that signs in.
    $this->post(route('login.store'), ['email' => 'ops@example.com', 'password' => $password])
        ->assertRedirect(route('backlog'));

    $this->assertAuthenticatedAs($user);
});

it('gives two operators different passwords', function () {
    $first = generatedPassword('user:register', ['--name' => 'One', '--email' => 'one@example.com']);
    $second = generatedPassword('user:register', ['--name' => 'Two', '--email' => 'two@example.com']);

    expect($first)->not->toBe($second);
});

it('refuses a duplicate address', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('user:register', ['--name' => 'Second', '--email' => 'taken@example.com'])
        ->assertFailed();

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

it('refuses an address that is not one', function () {
    $this->artisan('user:register', ['--name' => 'Ops', '--email' => 'not-an-address'])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('prompts for what it was not given', function () {
    $this->artisan('user:register')
        ->expectsQuestion('Name', 'Prompted')
        ->expectsQuestion('Email', 'prompted@example.com')
        ->assertSuccessful();

    expect(User::query()->where('email', 'prompted@example.com')->sole()->name)->toBe('Prompted');
});

it('never asks for a password', function () {
    // Asking would mean an operator could choose a weak one for the account
    // that reads every prompt this gateway has handled.
    $this->artisan('user:register', ['--name' => 'Ops', '--email' => 'ops@example.com'])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Password:');
});
