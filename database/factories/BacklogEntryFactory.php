<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BacklogEntry> */
class BacklogEntryFactory extends Factory
{
    public const CALLER_KEY = 'sk-test-caller';

    protected $model = BacklogEntry::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $model = fake()->randomElement(['qwen3-vl:30b-a3b', 'qwen3-vl:32b', 'llama3.2:3b']);

        return [
            'id' => BacklogEntry::newId(),
            'status' => BacklogStatus::Queued,
            'model' => $model,
            // The migration seeds a default gateway, so entries land on the
            // same upstream a real request without a prefix would.
            'gateway_id' => fn () => (Gateway::default() ?? Gateway::factory()->isDefault()->create())->id,
            'request' => [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => fake()->sentence()]],
            ],
            'headers' => [
                'authorization' => 'Bearer '.self::CALLER_KEY,
                'content-type' => 'application/json',
            ],
            'output' => null,
            'usage' => null,
            'error' => null,
            'attempts' => 0,
            'max_attempts' => 2,
            'api_key_hash' => hash('sha256', self::CALLER_KEY),
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => BacklogStatus::Running,
            'attempts' => 1,
            'started_at' => now(),
        ]);
    }

    public function stale(): static
    {
        return $this->running()->state(fn () => [
            'started_at' => now()->subHours(2),
        ]);
    }

    public function completed(string $text = 'PONG'): static
    {
        return $this->state(fn () => [
            'status' => BacklogStatus::Completed,
            'attempts' => 1,
            'started_at' => now()->subSeconds(5),
            'finished_at' => now(),
            'headers' => null,
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [['type' => 'output_text', 'text' => $text]],
            ]],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 3, 'total_tokens' => 15],
        ]);
    }

    public function failed(string $message = 'RuntimeException: upstream unavailable'): static
    {
        return $this->state(fn () => [
            'status' => BacklogStatus::Failed,
            'attempts' => 2,
            'started_at' => now()->subSeconds(5),
            'finished_at' => now(),
            'headers' => null,
            'error' => ['type' => 'exception', 'message' => $message, 'attempts' => 2],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BacklogStatus::Cancelled,
            'headers' => null,
            'finished_at' => now(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['attempts' => 2, 'max_attempts' => 2]);
    }
}
