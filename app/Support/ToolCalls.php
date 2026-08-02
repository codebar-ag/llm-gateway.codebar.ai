<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The tool-call shapes this app has to translate between.
 *
 * Internally it speaks OpenAI chat completions, so that is the canonical form
 * here: a tool stated as `{type, function: {name, description, parameters}}`
 * and a call as `{id, type, function: {name, arguments}}` with the arguments
 * as a JSON *string*. Both neighbours differ from it — the Responses API
 * states a tool flat and returns a call as a `function_call` output item,
 * Ollama and Anthropic both hand the arguments back already decoded — and
 * this is the one place either difference is written down.
 *
 * The two directions judge differently, on purpose. Going up, nothing is ever
 * dropped: the upstream is what decides whether it can run a tool, and this
 * app hiding one it did not recognise would answer the caller's question
 * without the tool they asked for. Coming back, a call the upstream left
 * unnamed *is* dropped — the name is the only part a caller can dispatch on,
 * so forwarding one without it trades an answer that says the model asked for
 * nothing for one that asks for something nobody can run.
 */
class ToolCalls
{
    private const CHOICE_KEYWORDS = ['auto', 'none', 'required'];

    /**
     * The tool calls a completion asked for.
     *
     * @param  array<array-key, mixed>  $completion
     * @return array<int, array<string, mixed>>
     */
    public static function fromCompletion(array $completion): array
    {
        return self::fromMessage(JsonValue::at($completion, 'choices.0.message'));
    }

    /**
     * The tool calls carried by one assistant message.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromMessage(mixed $message): array
    {
        return collect(JsonValue::arrayAt($message, 'tool_calls'))
            ->map(self::call(...))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A Responses API `function_call` input item, as a chat tool call.
     *
     * @return array<string, mixed>
     */
    public static function fromInputItem(mixed $item): array
    {
        return [
            'id' => JsonValue::stringAt($item, 'call_id') ?: JsonValue::stringAt($item, 'id'),
            'type' => 'function',
            'function' => [
                'name' => JsonValue::stringAt($item, 'name'),
                'arguments' => self::arguments(JsonValue::at($item, 'arguments')),
            ],
        ];
    }

    /**
     * Tool calls as the Responses API output items a caller polls for.
     *
     * `call_id` is what a caller echoes back on the next turn; `id` names the
     * output item itself. They are separate fields in the spec and separate
     * here, rather than one value written twice.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    public static function outputItems(array $toolCalls): array
    {
        return array_map(fn (array $call): array => [
            'type' => 'function_call',
            'id' => 'fc_'.Str::uuid7()->getHex(),
            'call_id' => JsonValue::stringAt($call, 'id'),
            'name' => JsonValue::stringAt($call, 'function.name'),
            'arguments' => JsonValue::stringAt($call, 'function.arguments'),
            'status' => 'completed',
        ], $toolCalls);
    }

    /**
     * A `tools` list in chat form, accepting the Responses API's flat
     * statement of the same thing.
     *
     * Only a flat function is restated. Everything else travels exactly as the
     * caller wrote it — a hosted tool, a malformed one, a shape this app has
     * never seen — because the upstream is the one that can say whether it can
     * run it. Dropping a `web_search` the caller asked for would leave a model
     * answering from memory and the caller reading it as a searched answer;
     * forwarding it makes the upstream's own rejection the report.
     *
     * @return array<int, mixed>
     */
    public static function tools(mixed $tools): array
    {
        return collect(Arr::wrap($tools))
            ->map(self::tool(...))
            ->values()
            ->all();
    }

    /**
     * A `tool_choice` in chat form.
     *
     * The three keywords are spelled the same in both APIs; only a forced tool
     * differs, named at the top level by the Responses API and nested under
     * `function` by chat completions.
     *
     * @return string|array<string, mixed>|null
     */
    public static function choice(mixed $choice): string|array|null
    {
        if (is_string($choice)) {
            return in_array($choice, self::CHOICE_KEYWORDS, true) ? $choice : null;
        }

        $name = JsonValue::stringAt($choice, 'function.name') ?: JsonValue::stringAt($choice, 'name');

        return $name === '' ? null : ['type' => 'function', 'function' => ['name' => $name]];
    }

    /**
     * Arguments as the JSON string an OpenAI caller expects to decode.
     *
     * Ollama and Anthropic both report them as an object. Handing that on
     * unchanged would put a decoded value where every OpenAI SDK calls
     * `json_decode` on a string, so it is re-encoded instead.
     */
    public static function arguments(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value) ?: '{}';
        }

        if ($value === null || $value === []) {
            return '{}';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * The inverse, for the upstreams that want the object back.
     *
     * @return array<array-key, mixed>
     */
    public static function decodedArguments(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode(JsonValue::asString($value), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The calls in an output list, one per line.
     *
     * For the dashboard, which would otherwise render a turn that only asked
     * for a tool as an entry that completed with no answer at all.
     *
     * @param  array<array-key, mixed>  $output
     */
    public static function summarise(array $output): ?string
    {
        $calls = collect($output)
            ->filter(fn (mixed $item) => JsonValue::stringAt($item, 'type') === 'function_call')
            ->map(fn (mixed $item) => JsonValue::stringAt($item, 'name').'('.JsonValue::stringAt($item, 'arguments').')')
            ->values();

        return $calls->isEmpty() ? null : $calls->implode("\n");
    }

    /**
     * One call, read from either the chat shape or Ollama's flatter one.
     *
     * An upstream that reports no id gets one minted here. The id is what ties
     * a result the caller sends back to the call that asked for it, and a
     * conversation cannot state that link if nothing ever named it.
     *
     * @return array<string, mixed>|null
     */
    private static function call(mixed $call): ?array
    {
        $name = JsonValue::stringAt($call, 'function.name') ?: JsonValue::stringAt($call, 'name');

        if ($name === '') {
            return null;
        }

        return [
            'id' => JsonValue::stringAt($call, 'id') ?: 'call_'.Str::uuid7()->getHex(),
            'type' => 'function',
            'function' => [
                'name' => $name,
                'arguments' => self::arguments(
                    JsonValue::at($call, 'function.arguments') ?? JsonValue::at($call, 'arguments'),
                ),
            ],
        ];
    }

    /**
     * The one restatement: a function the Responses API stated flat becomes
     * the nested chat form. A tool already nested, one of another type, and
     * one that named nothing all travel untouched.
     */
    private static function tool(mixed $tool): mixed
    {
        $name = JsonValue::stringAt($tool, 'name');

        if ($name === '' || JsonValue::stringAt($tool, 'type') !== 'function') {
            return $tool;
        }

        return ['type' => 'function', 'function' => array_filter([
            'name' => $name,
            'description' => JsonValue::stringAt($tool, 'function.description')
                ?: JsonValue::stringAt($tool, 'description'),
            'parameters' => JsonValue::at($tool, 'function.parameters') ?? JsonValue::at($tool, 'parameters'),
            // Carried rather than dropped: it is the caller's own constraint on
            // what the model may answer with, and an upstream that ignores the
            // field is a smaller surprise than one that was never told.
            'strict' => JsonValue::at($tool, 'function.strict') ?? JsonValue::at($tool, 'strict'),
        ], fn (mixed $value) => $value !== null && $value !== '')];
    }
}
