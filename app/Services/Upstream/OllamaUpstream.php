<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Support\ChatPayload;
use App\Support\JsonValue;
use Illuminate\Support\Collection;

/**
 * Ollama's native /api/chat.
 *
 * Ollama ignores an Authorization header, but the caller's headers are
 * forwarded anyway: an Ollama box exposed through an authenticating reverse
 * proxy is common, and dropping the credential would break it for no gain —
 * which is exactly `Upstream`'s default, so no header mapping lives here.
 *
 * `stream` is pinned false. This app answers by polling, so a streamed
 * response would arrive as newline-delimited JSON that nothing here reads.
 *
 * Moderations are left to `Upstream`'s passthrough: Ollama has no moderation
 * model or endpoint of its own, so the request is forwarded as-is and
 * Ollama's own 404 tells the caller this upstream cannot answer it.
 */
class OllamaUpstream extends Upstream
{
    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normalise(array $body): array
    {
        return [
            'id' => JsonValue::stringAt($body, 'created_at'),
            'object' => 'chat.completion',
            'model' => JsonValue::stringAt($body, 'model'),
            'choices' => [[
                'index' => 0,
                'message' => array_filter([
                    'role' => JsonValue::stringAt($body, 'message.role') ?: 'assistant',
                    'content' => JsonValue::stringAt($body, 'message.content'),
                    // Thinking models put the answer here when content is empty,
                    // which is the fallback ChatPayload::text() already knows.
                    'reasoning_content' => JsonValue::stringAt($body, 'message.thinking'),
                ], fn (string $value) => $value !== ''),
                'finish_reason' => $this->finishReason(JsonValue::stringAt($body, 'done_reason')),
            ]],
            'usage' => self::usage(
                JsonValue::intAt($body, 'prompt_eval_count'),
                JsonValue::intAt($body, 'eval_count'),
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
            'data' => collect(JsonValue::arrayAt($body, 'models'))
                ->map(fn (mixed $model) => [
                    'id' => JsonValue::stringAt($model, 'name') ?: JsonValue::stringAt($model, 'model'),
                    'object' => 'model',
                    'created' => self::timestamp(JsonValue::stringAt($model, 'modified_at')),
                    'owned_by' => 'ollama',
                ])
                ->filter(fn (array $model) => $model['id'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normaliseEmbeddings(array $body): array
    {
        // Ollama reports one count for the whole batch, not per input; OpenAI
        // has no batch-level field for it, so it is repeated into the total.
        $promptTokens = JsonValue::intAt($body, 'prompt_eval_count');

        return [
            'object' => 'list',
            'data' => collect(JsonValue::arrayAt($body, 'embeddings'))
                ->values()
                ->map(fn (mixed $embedding, int $index) => [
                    'object' => 'embedding',
                    'embedding' => is_array($embedding) ? array_values($embedding) : [],
                    'index' => $index,
                ])
                ->all(),
            'model' => JsonValue::stringAt($body, 'model'),
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'total_tokens' => $promptTokens,
            ],
        ];
    }

    /**
     * Ollama's native `/api/embed` takes the same `model` and `input` keys
     * OpenAI's embeddings request does, so nothing is renamed — only the
     * keys Ollama does not read are dropped, and the answer is reshaped.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function embeddingsBody(array $payload): array
    {
        return array_filter([
            'model' => JsonValue::stringAt($payload, 'model'),
            'input' => JsonValue::at($payload, 'input'),
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function body(array $payload): array
    {
        return array_filter([
            'model' => JsonValue::stringAt($payload, 'model'),
            'messages' => $this->messages($payload),
            'stream' => false,
            'options' => $this->options($payload),
        ], fn (mixed $value) => $value !== '' && $value !== []);
    }

    /**
     * Ollama takes a system role inline, so unlike Anthropic nothing is
     * hoisted — only multimodal content parts are flattened, since Ollama
     * carries images in a separate `images` key this driver does not fill.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function messages(array $payload): array
    {
        $system = ChatPayload::systemPrompt($payload);

        return collect(ChatPayload::conversation($payload))
            ->map(fn (array $message) => [
                'role' => JsonValue::stringAt($message, 'role') ?: 'user',
                'content' => ChatPayload::contentText(JsonValue::at($message, 'content')),
            ])
            ->when($system !== '', fn (Collection $messages) => $messages->prepend([
                'role' => 'system',
                'content' => $system,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function options(array $payload): array
    {
        return array_filter([
            'temperature' => JsonValue::at($payload, 'temperature'),
            'top_p' => JsonValue::at($payload, 'top_p'),
            'num_predict' => JsonValue::intAt($payload, 'max_tokens')
                ?? JsonValue::intAt($payload, 'max_completion_tokens'),
        ], fn (mixed $value) => $value !== null);
    }

    private function finishReason(string $doneReason): ?string
    {
        return match ($doneReason) {
            'stop' => 'stop',
            'length' => 'length',
            default => null,
        };
    }
}
