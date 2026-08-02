<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Support\ChatPayload;
use App\Support\JsonValue;
use App\Support\ToolCalls;
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
        // Ollama reports a call with the arguments decoded and, before 0.4,
        // with no id at all; `ToolCalls` restates both into the OpenAI form.
        $toolCalls = ToolCalls::fromMessage(JsonValue::at($body, 'message'));

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
                    'tool_calls' => $toolCalls,
                ], fn (mixed $value) => $value !== '' && $value !== []),
                // Ollama reports `stop` whether or not the turn asked for a
                // tool; a caller keys off the finish reason to know it did.
                'finish_reason' => $toolCalls === []
                    ? $this->finishReason(JsonValue::stringAt($body, 'done_reason'))
                    : 'tool_calls',
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
            // Ollama declares a tool exactly the way chat completions does, so
            // this is a filter for what it cannot run rather than a rename.
            'tools' => ToolCalls::tools(JsonValue::at($payload, 'tools')),
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
        $conversation = ChatPayload::conversation($payload);
        $names = self::toolNames($conversation);

        return collect($conversation)
            ->map(fn (array $message) => [
                'role' => JsonValue::stringAt($message, 'role') ?: 'user',
                'content' => ChatPayload::contentText(JsonValue::at($message, 'content')),
                ...array_filter([
                    'tool_name' => $names[JsonValue::stringAt($message, 'tool_call_id')] ?? '',
                    'tool_calls' => $this->toolCalls($message),
                ], fn (mixed $value) => $value !== '' && $value !== []),
            ])
            ->when($system !== '', fn (Collection $messages) => $messages->prepend([
                'role' => 'system',
                'content' => $system,
            ]))
            ->values()
            ->all();
    }

    /**
     * Which tool each call id was for.
     *
     * Ollama identifies a result by the tool's name where OpenAI matches on
     * the call id, so the name has to be read back out of the assistant turn
     * that asked for it — the caller only ever sends the id.
     *
     * @param  array<int, array<array-key, mixed>>  $conversation
     * @return array<string, string>
     */
    private static function toolNames(array $conversation): array
    {
        return collect($conversation)
            ->flatMap(fn (array $message) => ToolCalls::fromMessage($message))
            ->mapWithKeys(fn (array $call) => [
                JsonValue::stringAt($call, 'id') => JsonValue::stringAt($call, 'function.name'),
            ])
            ->filter()
            ->all();
    }

    /**
     * Ollama nests a call under `function` like OpenAI but wants the arguments
     * as an object rather than the JSON string they are stored as, and reads
     * no id — it is dropped rather than sent somewhere it means nothing.
     *
     * @param  array<array-key, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    private function toolCalls(array $message): array
    {
        return array_map(fn (array $call): array => [
            'function' => [
                'name' => JsonValue::stringAt($call, 'function.name'),
                'arguments' => (object) ToolCalls::decodedArguments(JsonValue::at($call, 'function.arguments')),
            ],
        ], ToolCalls::fromMessage($message));
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
