<?php

namespace Database\Seeders;

use App\Enums\GuardEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::updateOrCreate([
            'name' => RoleEnum::ADMINISTRATOR->value,
            'guard_name' => GuardEnum::WEB->value,
        ]);

        Role::updateOrCreate([
            'name' => RoleEnum::USER->value,
            'guard_name' => GuardEnum::WEB->value,
        ]);
    }
}
