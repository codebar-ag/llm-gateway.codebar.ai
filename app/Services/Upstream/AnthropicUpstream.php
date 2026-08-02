<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Support\ChatPayload;
use App\Support\JsonValue;
use Illuminate\Support\Str;

/**
 * Anthropic's native Messages API.
 *
 * Two things differ from an OpenAI-compatible upstream and both are handled
 * here rather than asked of the caller: the credential travels in `x-api-key`
 * instead of `Authorization`, and `system` is a top-level field rather than a
 * role inside `messages`. The key is still the caller's own — this moves it
 * to the header Anthropic reads, it does not supply one.
 *
 * Embeddings and moderations are left to `Upstream`'s passthrough. Anthropic
 * has neither API, so there is nothing of its own to translate the body into
 * — the request goes up as the caller sent it and Anthropic's own 404 is what
 * tells the caller this upstream cannot answer it.
 */
class AnthropicUpstream extends Upstream
{
    /** Anthropic rejects a request without max_tokens; OpenAI treats it as optional. */
    private const DEFAULT_MAX_TOKENS = 4096;

    private const DEFAULT_VERSION = '2023-06-01';

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normalise(array $body): array
    {
        return [
            'id' => JsonValue::stringAt($body, 'id'),
            'object' => 'chat.completion',
            'model' => JsonValue::stringAt($body, 'model'),
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => JsonValue::stringAt($body, 'role') ?: 'assistant',
                    'content' => $this->answer($body),
                ],
                'finish_reason' => $this->finishReason(JsonValue::stringAt($body, 'stop_reason')),
            ]],
            'usage' => self::usage(
                JsonValue::intAt($body, 'usage.input_tokens'),
                JsonValue::intAt($body, 'usage.output_tokens'),
            ),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normaliseModels(array $body): array
    {
        return [
            'object' => 'list',
            'data' => collect(JsonValue::arrayAt($body, 'data'))
                ->map(fn (mixed $model) => [
                    'id' => JsonValue::stringAt($model, 'id'),
                    'object' => 'model',
                    'created' => self::timestamp(JsonValue::stringAt($model, 'created_at')),
                    'owned_by' => 'anthropic',
                ])
                ->filter(fn (array $model) => $model['id'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function headers(array $headers): array
    {
        $headers = array_change_key_case($headers);

        $key = $this->bearer($headers['authorization'] ?? '');

        unset($headers['authorization']);

        return array_filter([
            ...$headers,
            'x-api-key' => $key,
            'anthropic-version' => $headers['anthropic-version'] ?? self::DEFAULT_VERSION,
        ], fn (string $value) => $value !== '');
    }

    private function bearer(string $authorization): string
    {
        return trim(Str::startsWith(Str::lower($authorization), 'bearer ')
            ? Str::after($authorization, ' ')
            : $authorization);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function body(array $payload): array
    {
        $system = ChatPayload::systemPrompt($payload);

        return array_filter([
            'model' => JsonValue::stringAt($payload, 'model'),
            'max_tokens' => $this->maxTokens($payload),
            'messages' => ChatPayload::conversation($payload),
            'system' => $system,
            'temperature' => JsonValue::at($payload, 'temperature'),
            'top_p' => JsonValue::at($payload, 'top_p'),
            'stop_sequences' => $this->stopSequences($payload),
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== []);
    }

    /** @param  array<string, mixed>  $payload */
    private function maxTokens(array $payload): int
    {
        return JsonValue::intAt($payload, 'max_tokens')
            ?? JsonValue::intAt($payload, 'max_completion_tokens')
            ?? JsonValue::intAt($payload, 'max_output_tokens')
            ?? self::DEFAULT_MAX_TOKENS;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function stopSequences(array $payload): array
    {
        $stop = JsonValue::at($payload, 'stop');

        if ($stop === null) {
            return [];
        }

        return collect(is_array($stop) ? $stop : [$stop])
            ->map(fn (mixed $value) => JsonValue::asString($value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Anthropic returns content as blocks; only the text ones have an
     * OpenAI equivalent, and a tool_use block leaves the answer empty rather
     * than inventing a translation for it.
     *
     * @param  array<array-key, mixed>  $body
     */
    private function answer(array $body): string
    {
        return collect(JsonValue::arrayAt($body, 'content'))
            ->filter(fn (mixed $block) => JsonValue::stringAt($block, 'type') === 'text')
            ->map(fn (mixed $block) => JsonValue::stringAt($block, 'text'))
            ->filter()
            ->implode("\n");
    }

    private function finishReason(string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn', 'stop_sequence' => 'stop',
            'max_tokens' => 'length',
            'tool_use' => 'tool_calls',
            default => null,
        };
    }
}
