<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => $this->faker->colorName(),
            'description' => $this->faker->text(),
            'subject_type' => User::class,
            'event' => $this->faker->colorName(),
            'subject_id' => User::factory(),
            'causer_type' => User::class,
            'causer_id' => User::factory(),
        ];
    }
}
