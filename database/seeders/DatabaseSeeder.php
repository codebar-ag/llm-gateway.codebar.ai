<?php

namespace Database\Seeders;

use App\Enums\GuardEnum;
use App\Enums\LocaleEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        Role::findOrCreate(RoleEnum::ADMINISTRATOR->value, GuardEnum::WEB->value);
        Role::findOrCreate(RoleEnum::USER->value, GuardEnum::WEB->value);

        $user = User::updateOrCreate(
            ['email' => 'info@codebar.ch'],
            [
                'name' => 'codebar Solutions AG',
                'password' => bcrypt('password'),
                'locale' => LocaleEnum::DE,
            ],
        );

        $user->markEmailAsVerified();

        $user->assignRole(RoleEnum::ADMINISTRATOR, RoleEnum::USER);
    }
}
