<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GeneratedPassword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\text;

/**
 * The only way an account comes into existence.
 *
 * There is no registration route, and the password is generated rather than
 * chosen — see App\Support\GeneratedPassword. Requiring a shell on the server
 * is the point: it means the dashboard has no unauthenticated route that can
 * mint access. `user:reset-password` is the matching way back in.
 */
#[Signature('user:register {--name= : The operator\'s name} {--email= : The address they log in with}')]
#[Description('Create a dashboard operator account with a generated password')]
class RegisterUserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?? text(label: 'Name', required: true);
        $email = $this->option('email') ?? text(label: 'Email', required: true);
        $password = GeneratedPassword::make();

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        /** @var array<string, mixed> $attributes */
        $attributes = $validator->validated();

        // The model's `hashed` cast does the hashing.
        $user = User::query()->create([...$attributes, 'password' => $password]);

        $this->components->info("Registered {$user->email}.");
        $this->components->twoColumnDetail('Password', $password);
        $this->components->warn('Shown once and never stored in readable form. Run user:reset-password to issue a new one.');

        return self::SUCCESS;
    }
}
