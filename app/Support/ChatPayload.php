<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reading and reshaping the OpenAI chat-completion payloads this app stores.
 *
 * Nothing here decides where a request goes or rewrites it on the way out —
 * that is a driver's job. These are the two translations the app already owed
 * its callers: Responses input into chat messages on the way in, and answer
 * text out of a completion on the way back. The tool shapes either direction
 * carries are `ToolCalls`' business, not this class's.
 */
class ChatPayload
{
    private const TEXT_TYPES = ['input_text', 'output_text', 'text'];

    private const IMAGE_TYPES = ['input_image', 'image_url'];

    /**
     * Answer text of a chat completion.
     *
     * Reasoning models leave `content` empty and write everything to
     * `reasoning_content`; without that fallback the caller gets nothing.
     *
     * @param  array<array-key, mixed>  $completion
     */
    public static function text(array $completion): string
    {
        $message = JsonValue::at($completion, 'choices.0.message');

        $text = JsonValue::stringAt($message, 'content') ?: JsonValue::stringAt($message, 'reasoning_content');

        return self::withoutModelArtifacts($text);
    }

    /**
     * Translate Responses API input into chat messages.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public static function toMessages(array $payload): array
    {
        $instructions = JsonValue::at($payload, 'instructions');

        return self::inputMessages(JsonValue::at($payload, 'input'))
            ->when(filled($instructions), fn (Collection $messages) => $messages->prepend([
                'role' => 'system',
                'content' => $instructions,
            ]))
            ->values()
            ->all();
    }

    /**
     * The messages of a stored chat request, without the system ones.
     *
     * Anthropic and Ollama both take a conversation that starts at the user;
     * Anthropic refuses a `system` role inside `messages` outright.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<array-key, mixed>>
     */
    public static function conversation(array $payload): array
    {
        return self::messages($payload)
            ->reject(fn (array $message) => JsonValue::stringAt($message, 'role') === 'system')
            ->values()
            ->all();
    }

    /**
     * The system messages of a stored chat request, joined into one string.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function systemPrompt(array $payload): string
    {
        return self::messages($payload)
            ->filter(fn (array $message) => JsonValue::stringAt($message, 'role') === 'system')
            ->map(fn (array $message) => self::contentText(JsonValue::at($message, 'content')))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Flatten a message's content to plain text.
     *
     * Multimodal parts collapse to their text: the native Anthropic and Ollama
     * bodies carry images differently enough that guessing is worse than
     * dropping them, and dropping them is visible in the answer.
     */
    public static function contentText(mixed $content): string
    {
        if (! is_array($content)) {
            return JsonValue::asString($content);
        }

        return collect($content)
            ->map(fn (mixed $part) => JsonValue::stringAt($part, 'text'))
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array<array-key, mixed>>
     */
    private static function messages(array $payload): Collection
    {
        return collect(Arr::wrap(JsonValue::at($payload, 'messages')))
            ->filter(fn (mixed $message) => is_array($message))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function inputMessages(mixed $input): Collection
    {
        if (is_string($input)) {
            return collect([self::message($input)]);
        }

        /** @var Collection<int, array<string, mixed>> $empty */
        $empty = collect();

        return collect(Arr::wrap($input))
            ->reduce(self::append(...), $empty)
            ->values();
    }

    /**
     * Fold one input item onto the conversation built so far.
     *
     * Two of them are not messages at all. A `function_call` is the assistant's
     * own turn restated as an output item, and a `function_call_output` is the
     * result the caller ran — chat completions spells those as an assistant
     * message carrying `tool_calls` and a `tool` message answering one.
     *
     * @param  Collection<int, array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    private static function append(Collection $messages, mixed $item): Collection
    {
        return match (JsonValue::stringAt($item, 'type')) {
            'function_call' => self::appendToolCall($messages, ToolCalls::fromInputItem($item)),
            'function_call_output' => $messages->push([
                'role' => 'tool',
                'tool_call_id' => JsonValue::stringAt($item, 'call_id'),
                'content' => self::contentText(JsonValue::at($item, 'output')),
            ]),
            default => $messages->push(self::message($item)),
        };
    }

    /**
     * Merge consecutive calls into one assistant message.
     *
     * A turn is one assistant message carrying N calls followed by N results.
     * Stating the same turn as N assistant messages of one call each is what
     * an upstream rejects, so the fold appends rather than pushes.
     *
     * @param  Collection<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $call
     * @return Collection<int, array<string, mixed>>
     */
    private static function appendToolCall(Collection $messages, array $call): Collection
    {
        $last = $messages->last();

        if ($last === null || ! is_array($last['tool_calls'] ?? null)) {
            return $messages->push(['role' => 'assistant', 'content' => null, 'tool_calls' => [$call]]);
        }

        $last['tool_calls'] = [...$last['tool_calls'], $call];

        return $messages->slice(0, -1)->push($last)->values();
    }

    /** @return array<string, mixed> */
    private static function message(mixed $item): array
    {
        if (is_string($item)) {
            return ['role' => 'user', 'content' => $item];
        }

        $role = JsonValue::stringAt($item, 'role') ?: 'user';
        $content = JsonValue::at($item, 'content');

        if (is_string($content)) {
            return ['role' => $role, 'content' => $content];
        }

        return ['role' => $role, 'content' => self::contentParts($content)];
    }

    /** @return array<int, array<string, mixed>> */
    private static function contentParts(mixed $content): array
    {
        return collect(Arr::wrap($content))
            ->map(self::contentPart(...))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private static function contentPart(mixed $part): ?array
    {
        $type = JsonValue::stringAt($part, 'type');

        if (in_array($type, self::TEXT_TYPES, true)) {
            return ['type' => 'text', 'text' => JsonValue::stringAt($part, 'text')];
        }

        if (in_array($type, self::IMAGE_TYPES, true)) {
            $image = JsonValue::at($part, 'image_url');

            return [
                'type' => 'image_url',
                'image_url' => ['url' => JsonValue::stringAt($image, 'url') ?: JsonValue::asString($image)],
            ];
        }

        return null;
    }

    private static function withoutModelArtifacts(string $text): string
    {
        $answer = Str::afterLast($text, '</think>');

        return trim((string) preg_replace('/<\|im_(start|end)\|>/', '', $answer));
    }
}
