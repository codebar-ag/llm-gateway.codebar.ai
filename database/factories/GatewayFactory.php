<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Gateway> */
class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'protocol' => GatewayProtocol::OpenAi,
            'base_url' => 'http://litellm.test',
            'path' => null,
            'default_model' => null,
            'timeout' => 900,
            'connect_timeout' => 10,
            'is_default' => false,
            'is_active' => true,
            'description' => null,
        ];
    }

    /**
     * The gateway a request without a UUID prefix lands on.
     *
     * The migration seeds one already, so tests that want their own default
     * have to clear it first — `Gateways::makeDefault()` is the supported way.
     */
    public function isDefault(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function anthropic(): static
    {
        return $this->state(fn () => [
            'protocol' => GatewayProtocol::Anthropic,
            'base_url' => 'https://anthropic.test',
        ]);
    }

    public function ollama(): static
    {
        return $this->state(fn () => [
            'protocol' => GatewayProtocol::Ollama,
            'base_url' => 'http://ollama.test:11434',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
