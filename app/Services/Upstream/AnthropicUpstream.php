<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Support\ChatPayload;
use App\Support\JsonValue;
use App\Support\ToolCalls;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Anthropic's native Messages API.
 *
 * Three things differ from an OpenAI-compatible upstream and all are handled
 * here rather than asked of the caller: the credential travels in `x-api-key`
 * instead of `Authorization`, `system` is a top-level field rather than a role
 * inside `messages`, and tools are content blocks inside the conversation
 * rather than a `tool_calls` array beside it. The key is still the caller's
 * own — this moves it to the header Anthropic reads, it does not supply one.
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
                'message' => array_filter([
                    'role' => JsonValue::stringAt($body, 'role') ?: 'assistant',
                    'content' => $this->answer($body),
                    'tool_calls' => $this->toolCalls($body),
                ], fn (mixed $value) => $value !== []),
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
            'messages' => $this->messages($payload),
            'system' => $system,
            'temperature' => JsonValue::at($payload, 'temperature'),
            'top_p' => JsonValue::at($payload, 'top_p'),
            'stop_sequences' => $this->stopSequences($payload),
            'tools' => $this->tools($payload),
            'tool_choice' => $this->toolChoice($payload),
        ], fn (mixed $value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Anthropic states a tool flat, with the JSON Schema under `input_schema`
     * rather than nested as `function.parameters`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    private function tools(array $payload): array
    {
        return array_map($this->tool(...), ToolCalls::tools(JsonValue::at($payload, 'tools')));
    }

    /**
     * Only a function is restated. Anything else travels as the caller wrote
     * it — Anthropic's own server tools are declared by a versioned type this
     * driver has no business rewriting, and a shape it cannot read is
     * Anthropic's to reject rather than this app's to quietly drop.
     */
    private function tool(mixed $tool): mixed
    {
        $name = JsonValue::stringAt($tool, 'function.name');

        if ($name === '') {
            return $tool;
        }

        return array_filter([
            'name' => $name,
            'description' => JsonValue::stringAt($tool, 'function.description'),
            'input_schema' => $this->inputSchema($tool),
        ], fn (mixed $value) => $value !== '');
    }

    /**
     * Anthropic rejects a tool with no schema, and rejects an empty one stated
     * as a JSON array — which is what an empty PHP array encodes to. A tool
     * that takes no arguments therefore declares an object with no properties.
     *
     * @return array<array-key, mixed>
     */
    private function inputSchema(mixed $tool): array
    {
        $schema = JsonValue::at($tool, 'function.parameters');

        if (is_array($schema) && $schema !== []) {
            return $schema;
        }

        return ['type' => 'object', 'properties' => (object) []];
    }

    /**
     * `auto` and a named tool are spelled differently; `required` is `any`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function toolChoice(array $payload): array
    {
        $choice = JsonValue::at($payload, 'tool_choice');

        if (is_string($choice)) {
            return match ($choice) {
                'auto' => ['type' => 'auto'],
                'required' => ['type' => 'any'],
                'none' => ['type' => 'none'],
                default => [],
            };
        }

        $name = JsonValue::stringAt($choice, 'function.name') ?: JsonValue::stringAt($choice, 'name');

        return $name === '' ? [] : ['type' => 'tool', 'name' => $name];
    }

    /**
     * The conversation as Anthropic blocks.
     *
     * Two rewrites the other roles do not need. An assistant turn that asked
     * for tools carries `tool_use` content blocks rather than a `tool_calls`
     * array beside the text, and a result comes back as a `tool_result` block
     * inside a *user* message — Anthropic has no tool role to put it in.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<array-key, mixed>>
     */
    private function messages(array $payload): array
    {
        /** @var Collection<int, array<array-key, mixed>> $empty */
        $empty = collect();

        return collect(ChatPayload::conversation($payload))
            ->reduce($this->appendMessage(...), $empty)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<array-key, mixed>>  $messages
     * @param  array<array-key, mixed>  $message
     * @return Collection<int, array<array-key, mixed>>
     */
    private function appendMessage(Collection $messages, array $message): Collection
    {
        if (JsonValue::stringAt($message, 'role') === 'tool') {
            return $this->appendToolResult($messages, $message);
        }

        $toolCalls = ToolCalls::fromMessage($message);

        if ($toolCalls === []) {
            return $messages->push($message);
        }

        return $messages->push([
            'role' => 'assistant',
            'content' => [
                ...$this->textBlocks(JsonValue::at($message, 'content')),
                ...array_map(fn (array $call): array => [
                    'type' => 'tool_use',
                    'id' => JsonValue::stringAt($call, 'id'),
                    'name' => JsonValue::stringAt($call, 'function.name'),
                    'input' => (object) ToolCalls::decodedArguments(JsonValue::at($call, 'function.arguments')),
                ], $toolCalls),
            ],
        ]);
    }

    /**
     * Results merge into the user message a previous one already opened.
     *
     * Anthropic requires the roles to alternate, so two results answering the
     * same assistant turn cannot be two user messages — they are two blocks of
     * one. Only a message this method built is merged into: a `tool_result`
     * appended to a user's own multimodal turn would be a different claim.
     *
     * @param  Collection<int, array<array-key, mixed>>  $messages
     * @param  array<array-key, mixed>  $message
     * @return Collection<int, array<array-key, mixed>>
     */
    private function appendToolResult(Collection $messages, array $message): Collection
    {
        $block = [
            'type' => 'tool_result',
            'tool_use_id' => JsonValue::stringAt($message, 'tool_call_id'),
            'content' => ChatPayload::contentText(JsonValue::at($message, 'content')),
        ];

        $last = $messages->last();

        if ($last === null || ! $this->isToolResultMessage($last)) {
            return $messages->push(['role' => 'user', 'content' => [$block]]);
        }

        $last['content'] = [...$last['content'], $block];

        return $messages->slice(0, -1)->push($last)->values();
    }

    /**
     * @param  array<array-key, mixed>  $message
     *
     * @phpstan-assert-if-true array{content: array<array-key, mixed>} $message
     */
    private function isToolResultMessage(array $message): bool
    {
        $content = JsonValue::at($message, 'content');

        return JsonValue::stringAt($message, 'role') === 'user'
            && is_array($content)
            && $content !== []
            && collect($content)->every(fn (mixed $block) => JsonValue::stringAt($block, 'type') === 'tool_result');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function textBlocks(mixed $content): array
    {
        $text = ChatPayload::contentText($content);

        return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
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
     * Anthropic returns content as blocks. The text ones are the answer; the
     * `tool_use` ones are read separately by `toolCalls()`, and a turn made of
     * nothing but those answers with an empty string on purpose.
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

    /**
     * `tool_use` blocks as OpenAI tool calls.
     *
     * Anthropic reports the arguments decoded; `ToolCalls::arguments()` puts
     * them back into the JSON string a caller's SDK expects to parse.
     *
     * @param  array<array-key, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    private function toolCalls(array $body): array
    {
        return collect(JsonValue::arrayAt($body, 'content'))
            ->filter(fn (mixed $block) => JsonValue::stringAt($block, 'type') === 'tool_use')
            ->map(fn (mixed $block) => [
                'id' => JsonValue::stringAt($block, 'id'),
                'type' => 'function',
                'function' => [
                    'name' => JsonValue::stringAt($block, 'name'),
                    'arguments' => ToolCalls::arguments(JsonValue::at($block, 'input')),
                ],
            ])
            ->values()
            ->all();
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
