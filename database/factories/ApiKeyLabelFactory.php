<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKeyLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiKeyLabel> */
class ApiKeyLabelFactory extends Factory
{
    protected $model = ApiKeyLabel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'api_key_hash' => hash('sha256', fake()->unique()->uuid()),
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /** The label for entries queued without a bearer token. */
    public function anonymous(): static
    {
        return $this->state(fn () => ['api_key_hash' => ApiKeyLabel::ANONYMOUS]);
    }

    public function forKey(string $key): static
    {
        return $this->state(fn () => ['api_key_hash' => hash('sha256', $key)]);
    }
}
