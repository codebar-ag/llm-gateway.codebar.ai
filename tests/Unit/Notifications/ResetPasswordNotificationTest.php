<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

it('sends reset password notification with correct content', function () {
    Notification::fake();

    $user = User::factory()->create();

    DB::table('password_reset_tokens')->where('email', $user->email)->delete();

    $token = hash_hmac('sha256', Str::random(40), config('app.key'));

    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => $token,
        'created_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        'auth.reset-password',
        now()->addMinutes(60),
        ['token' => $token, 'email' => $user->email]
    );

    $user->notify(new ResetPasswordNotification($token, $url, 60));

    Notification::assertSentTo(
        notifiable: $user,
        notification: ResetPasswordNotification::class,
        callback: function (ResetPasswordNotification $notification, $channels, $notifiable) use ($user, $url) {
            $mailData = $notification->toMail($user)->toArray();

            $this->assertEquals(null, $mailData['subject']);
            $this->assertEquals(__('You are receiving this email because we received a password reset request for your account.'), $mailData['introLines'][0]);
            $this->assertEquals(__('Reset Password'), $mailData['actionText']);
            $this->assertEquals($url, $mailData['actionUrl']);
            $this->assertEquals(__('auth.passwords.expire', ['minutes' => 60]), $mailData['outroLines'][0]);
            $this->assertEquals(__('If you did not request a password reset, no further action is required.'), $mailData['outroLines'][1]);

            return true;
        }
    );
});

it('checks the toArray method of the notification', function () {
    $user = User::factory()->create();

    $token = hash_hmac('sha256', Str::random(40), config('app.key'));

    $url = URL::temporarySignedRoute(
        'auth.reset-password',
        now()->addMinutes(60),
        ['token' => $token, 'email' => $user->email]
    );

    $notification = new ResetPasswordNotification($token, $url, 60);

    $arrayData = $notification->toArray($user);

    expect($arrayData)->toMatchArray([
        'url' => $url,
        'expirationInMinutes' => 60,
    ]);
});
