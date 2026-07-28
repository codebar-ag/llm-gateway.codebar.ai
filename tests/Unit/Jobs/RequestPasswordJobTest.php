<?php

use App\Jobs\Auth\RequestPasswordJob;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('logs activity if user is unverified', function () {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    Activity::query()->delete();

    (new RequestPasswordJob($user->email))->handle();

    expect(Activity::count())->toBe(1);

})->group('unit', 'models');

test('no logs activity if user is verified', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Activity::query()->delete();

    (new RequestPasswordJob($user->email))->handle();

    expect(Activity::count())->toBe(0);

})->group('unit', 'models');

test('notifies verified user with the generated reset token, not the name', function () {
    Notification::fake();

    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email_verified_at' => now(),
    ]);

    (new RequestPasswordJob($user->email))->handle();

    $storedToken = DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->value('token');

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        fn (ResetPasswordNotification $notification): bool => Hash::check($notification->token, $storedToken)
            && $notification->token !== $user->name,
    );

})->group('unit', 'models');

test('stores the reset token hashed, not in plaintext', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    (new RequestPasswordJob($user->email))->handle();

    $storedToken = DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->value('token');

    Notification::assertSentTo(
        $user,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($storedToken): bool {
            expect($notification->token)->not->toBe($storedToken);

            return Hash::check($notification->token, $storedToken);
        },
    );

})->group('unit', 'models');
