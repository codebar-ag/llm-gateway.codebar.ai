<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The only way an account comes into existence.
 *
 * There is no registration route and no password reset, so this command is
 * both how the first operator is created and how a forgotten password is
 * replaced — by registering again under a new address, or by an operator with
 * database access. Requiring a shell on the server is the point: it means the
 * dashboard has no unauthenticated route that can mint or recover access.
 */
#[Signature('user:register {--name= : The operator\'s name} {--email= : The address they log in with} {--password= : Skips the prompt, for deploy scripts}')]
#[Description('Create a dashboard operator account')]
class RegisterUserCommand extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'Name',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'Email',
            required: true,
        );

        // `password()` never echoes, so a shoulder-surfer and the shell history
        // both come away empty. `--password` exists for deploy scripts and does
        // land in history — which is why it is not the default path.
        $secret = $this->option('password') ?? password(
            label: 'Password',
            required: true,
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:12'],
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
        $user = User::query()->create($attributes);

        $this->components->info("Registered {$user->email}. They can sign in at ".route('login').'.');

        return self::SUCCESS;
    }
}
