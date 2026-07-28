<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

test('unauthorized auth.request-password.store', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('auth.request-password.store'), [
        'email' => $user->email,
    ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
})->group('auth', 'request-password');

test('authorized auth.request-password.store', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('auth.request-password.store'), [
            'email' => $user->email,
        ])
        ->assertRedirect();
})->group('auth', 'request-password');
