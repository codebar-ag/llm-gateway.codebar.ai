<?php

namespace App\Console\Commands;

use App\Enums\GuardEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class GrantUserAdministratorCommand extends Command
{
    protected $signature = 'user:grant-administrator {email : The user email address}';

    protected $description = 'Grant the administrator role to a user without removing their other roles.';

    public function handle(): int
    {
        $validator = Validator::make(
            ['email' => (string) $this->argument('email')],
            ['email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            $this->components->error((string) Arr::get($validator->errors()->toArray(), 'email.0', 'Invalid email.'));

            return Command::FAILURE;
        }

        $email = (string) Arr::get($validator->validated(), 'email', '');

        try {
            $user = User::query()->where('email', $email)->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->components->error("No user found for email [{$email}].");

            return Command::FAILURE;
        }

        try {
            $role = Role::query()
                ->where('name', RoleEnum::ADMINISTRATOR->value)
                ->where('guard_name', GuardEnum::WEB->value)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->components->error('Administrator role is missing. Run RolesAndPermissionsSeeder.');

            return Command::FAILURE;
        }

        $user->roles()->syncWithoutDetaching([(int) $role->getKey()]);
        $user->forgetCachedPermissions();

        $this->components->info("Granted administrator role to [{$email}] (user id {$user->getKey()}).");

        return Command::SUCCESS;
    }
}
