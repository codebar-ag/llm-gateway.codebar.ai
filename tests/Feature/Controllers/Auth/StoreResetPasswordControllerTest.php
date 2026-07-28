<?php

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function capturePasswordResetToken(User $user): string
{
    $token = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    return (string) $token;
}

test('authorized auth.reset-password.store with valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->post(route('auth.request-password.store'), [
        'email' => $user->email,
    ]);

    $token = capturePasswordResetToken($user);

    $this->withoutMiddleware([ValidateSignature::class]);

    $password = Str::random();

    $response = $this->post(route('auth.reset-password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
})->group('auth', 'reset-password');

test('rejects an expired reset token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->post(route('auth.request-password.store'), [
        'email' => $user->email,
    ]);

    $token = capturePasswordResetToken($user);

    PasswordResetToken::query()
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $this->withoutMiddleware([ValidateSignature::class]);

    $password = Str::random();

    $response = $this->post(route('auth.reset-password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionHasErrors('email');
})->group('auth', 'reset-password');

test('unauthorized reset-password.store', function () {
    $token = Str::random();

    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('auth.reset-password.store', $token))
        ->assertRedirect();
})->group('auth', 'reset-password');

test('store with valid token but posts wrong email', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->post(route('auth.request-password.store'), [
        'email' => $user->email,
    ]);

    $entry = PasswordResetToken::first();

    $this->withoutMiddleware([ValidateSignature::class]);

    $password = Str::random();

    $response = $this->post(route('auth.reset-password.store'), [
        'token' => $entry->token,
        'email' => 'wrong@email.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionHasErrors();
    $response->assertRedirect();
})->group('auth', 'reset-password');

test('store with valid token but wrong email', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->post(route('auth.request-password.store'), [
        'email' => $user->email,
    ]);

    $entry = PasswordResetToken::first();

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->withoutMiddleware([ValidateSignature::class]);

    $password = Str::random();

    $response = $this->post(route('auth.reset-password.store'), [
        'token' => $entry->token,
        'email' => $otherUser->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertSessionHasErrors();
    $response->assertRedirect();
})->group('auth', 'reset-password');
