<?php

namespace App\Jobs\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class RequestPasswordJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public string $email
    ) {}

    public function handle(): void
    {
        $user = User::query()
            ->where('email', $this->email)
            ->whereNotNull('email_verified_at')
            ->exists();

        match ($user) {
            true => self::processRequest(),
            default => self::logRequest(),
        };
    }

    protected function logRequest(): void
    {
        activity('failed-password-reset-attempt')
            ->withProperties(['email' => $this->email])
            ->log('failed password reset attempt');
    }

    protected function processRequest(): void
    {
        $user = User::query()
            ->where('email', $this->email)
            ->sole();

        $table = 'password_reset_tokens';

        DB::table($table)->where('email', $this->email)->delete();

        $expirationInMinutes = (int) config('auth.passwords.users.expire', 60);

        $token = hash_hmac('sha256', Str::random(40), Config::string('app.key'));

        DB::table($table)->insert([
            'email' => $this->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            name: 'auth.reset-password',
            expiration: now()->addMinutes($expirationInMinutes),
            parameters: ['token' => $token, 'email' => $user->email]
        );

        $user->notify(new ResetPasswordNotification($token, $url, $expirationInMinutes)->locale($user->locale->value));
    }

    public function failed(Throwable $exception): void
    {
        activity('failed-password-reset-attempt')
            ->withProperties(['email' => $this->email, 'error' => $exception->getMessage()])
            ->log('password reset job failed');
    }
}
