<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The `hashed` cast on the model does the hashing, so this stays plaintext
     * — a test that needs to log in reads it from `password()` below.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::password(),
            'remember_token' => null,
        ];
    }

    /** The plaintext every factory-made user is created with. */
    public static function password(): string
    {
        return 'correct-horse-battery-staple';
    }
}
