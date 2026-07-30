<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GeneratedPassword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * The way back in after a forgotten password.
 *
 * A command rather than an emailed link, deliberately: a reset route would be
 * an unauthenticated way to take over the account that can read every prompt
 * and answer this gateway has handled, and there is no mail configuration to
 * send one with. Whoever runs this already has a shell on the server.
 */
#[Signature('user:reset-password {--email= : The account to issue a new password for}')]
#[Description('Issue a new generated password for a dashboard operator')]
class ResetUserPasswordCommand extends Command
{
    public function handle(): int
    {
        $email = $this->option('email') ?? text(label: 'Email', required: true);

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No operator with the address {$email}. Run user:register to create one.");

            return self::FAILURE;
        }

        $password = GeneratedPassword::make();

        $user->forceFill([
            'password' => $password,
            /*
             | Rotated so a "remember me" cookie issued against the old password
             | stops working. Nothing sets one today, but leaving a live token
             | behind would make this reset quietly incomplete the moment
             | something does.
             */
            'remember_token' => Str::random(60),
        ])->save();

        $this->components->info("New password for {$user->email}.");
        $this->components->twoColumnDetail('Password', $password);
        $this->components->warn('Any browser signed in as this operator is now signed out.');

        return self::SUCCESS;
    }
}
