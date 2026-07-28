<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

test('authorized auth.reset-password.index', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $table = 'password_reset_tokens';

    DB::table($table)->where('email', $user->email)->delete();

    $token = hash_hmac('sha256', Str::random(40), config('app.key'));

    DB::table($table)->insert([
        'email' => $user->email,
        'token' => $token,
        'created_at' => now(),
    ]);

    $url = URL::temporarySignedRoute(
        name: 'auth.reset-password',
        expiration: now()->addMinutes(60),
        parameters: ['token' => $token, 'email' => $user->email]
    );

    $this->get($url)->assertOk();
})->group('auth', 'reset-password');
