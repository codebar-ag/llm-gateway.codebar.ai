<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BacklogEntry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Refusing to seed a production backlog.');

            return;
        }

        BacklogEntry::factory()->count(20)->create();
        BacklogEntry::factory()->count(12)->completed()->create();
        BacklogEntry::factory()->count(4)->failed()->create();
        BacklogEntry::factory()->count(2)->running()->create();
        BacklogEntry::factory()->count(2)->cancelled()->create();
        BacklogEntry::factory()->stale()->create();
    }
}
