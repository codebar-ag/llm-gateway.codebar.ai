<?php

declare(strict_types=1);

use App\Models\Gateway;
use App\Services\Upstream\AnthropicUpstream;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function anthropicGateway(): Gateway
{
    return Gateway::factory()->anthropic()->make(['base_url' => 'https://anthropic.test']);
}

function sendToAnthropic(array $payload, array $headers = []): void
{
    Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi']]])]);

    (new AnthropicUpstream)->send(anthropicGateway(), $payload, $headers);
}

it('posts to the messages endpoint', function () {
    sendToAnthropic(['model' => 'claude-sonnet-4-5', 'messages' => []]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://anthropic.test/v1/messages');
});

it('moves the caller bearer token into x-api-key', function () {
    sendToAnthropic(['model' => 'm'], ['authorization' => 'Bearer sk-caller']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sk-caller')
        && $request->header('Authorization') === []);
});

it('accepts a raw key with no bearer prefix', function () {
    sendToAnthropic(['model' => 'm'], ['authorization' => 'sk-caller']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sk-caller'));
});

it('sends no key at all when the caller had none', function () {
    sendToAnthropic(['model' => 'm'], []);

    Http::assertSent(fn (Request $request) => $request->header('x-api-key') === []);
});

it('defaults the api version and lets the caller override it', function () {
    sendToAnthropic(['model' => 'm'], []);

    Http::assertSent(fn (Request $request) => $request->hasHeader('anthropic-version', '2023-06-01'));

    sendToAnthropic(['model' => 'm'], ['anthropic-version' => '2024-01-01']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('anthropic-version', '2024-01-01'));
});

it('hoists system messages out of the conversation', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'hi'],
    ]]);

    Http::assertSent(fn (Request $request) => $request['system'] === 'Be brief.'
        && $request['messages'] === [['role' => 'user', 'content' => 'hi']]);
});

it('always sends a max_tokens because anthropic requires one', function () {
    sendToAnthropic(['model' => 'm', 'messages' => []]);

    Http::assertSent(fn (Request $request) => $request['max_tokens'] === 4096);
});

it('prefers the max token limit the caller asked for', function () {
    sendToAnthropic(['model' => 'm', 'max_tokens' => 100]);

    Http::assertSent(fn (Request $request) => $request['max_tokens'] === 100);
});

it('maps stop into stop_sequences', function (mixed $stop, array $expected) {
    sendToAnthropic(['model' => 'm', 'stop' => $stop]);

    Http::assertSent(fn (Request $request) => $request['stop_sequences'] === $expected);
})->with([
    ['END', ['END']],
    [['A', 'B'], ['A', 'B']],
]);

it('leaves out sampling parameters the caller did not set', function () {
    sendToAnthropic(['model' => 'm', 'temperature' => 0.2]);

    Http::assertSent(fn (Request $request) => $request->data() === [
        'model' => 'm',
        'max_tokens' => 4096,
        'temperature' => 0.2,
    ]);
});

it('normalises an answer into a chat completion', function () {
    $completion = (new AnthropicUpstream)->normalise([
        'id' => 'msg_1',
        'model' => 'claude-sonnet-4-5',
        'role' => 'assistant',
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'text', 'text' => 'first'],
            ['type' => 'text', 'text' => 'second'],
        ],
        'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
    ]);

    expect($completion['id'])->toBe('msg_1')
        ->and($completion['object'])->toBe('chat.completion')
        ->and($completion['model'])->toBe('claude-sonnet-4-5')
        ->and($completion['choices'][0]['message'])->toBe(['role' => 'assistant', 'content' => "first\nsecond"])
        ->and($completion['choices'][0]['finish_reason'])->toBe('stop')
        ->and($completion['usage'])->toBe([
            'prompt_tokens' => 12,
            'completion_tokens' => 3,
            'total_tokens' => 15,
        ]);
});

it('maps stop reasons onto finish reasons', function (string $stopReason, ?string $expected) {
    $completion = (new AnthropicUpstream)->normalise(['stop_reason' => $stopReason]);

    expect($completion['choices'][0]['finish_reason'])->toBe($expected);
})->with([
    ['end_turn', 'stop'],
    ['stop_sequence', 'stop'],
    ['max_tokens', 'length'],
    ['tool_use', 'tool_calls'],
    ['something_new', null],
]);

it('leaves the answer empty when no text block came back', function () {
    $completion = (new AnthropicUpstream)->normalise([
        'content' => [['type' => 'tool_use', 'name' => 'get_weather', 'input' => []]],
    ]);

    expect($completion['choices'][0]['message']['content'])->toBe('');
});

it('reports unknown token counts as null rather than zero', function () {
    $completion = (new AnthropicUpstream)->normalise(['content' => []]);

    expect($completion['usage'])->toBe([
        'prompt_tokens' => null,
        'completion_tokens' => null,
        'total_tokens' => null,
    ]);
});

/* Absent counts stay unknown rather than being reported as a confident zero. */

it('reports a total of null when the answer carries no usage at all', function () {
    $usage = (new AnthropicUpstream)->normalise(['id' => 'msg_1'])['usage'];

    expect($usage['prompt_tokens'])->toBeNull()
        ->and($usage['completion_tokens'])->toBeNull()
        ->and($usage['total_tokens'])->toBeNull();
});

it('still totals when only one of the two counts is reported', function () {
    $usage = (new AnthropicUpstream)->normalise(['usage' => ['input_tokens' => 7]])['usage'];

    expect($usage['total_tokens'])->toBe(7);
});

it('lists models from the models endpoint with the same headers a message would get', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    (new AnthropicUpstream)->models(anthropicGateway(), ['authorization' => 'Bearer sk-caller']);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://anthropic.test/v1/models'
        && $request->hasHeader('x-api-key', 'sk-caller')
        && $request->header('Authorization') === []);
});

it('normalises a models list into the OpenAI shape', function () {
    $list = (new AnthropicUpstream)->normaliseModels([
        'data' => [
            ['type' => 'model', 'id' => 'claude-sonnet-4-5', 'display_name' => 'Claude Sonnet 4.5', 'created_at' => '2025-09-29T00:00:00Z'],
        ],
    ]);

    expect($list)->toBe([
        'object' => 'list',
        'data' => [[
            'id' => 'claude-sonnet-4-5',
            'object' => 'model',
            'created' => strtotime('2025-09-29T00:00:00Z'),
            'owned_by' => 'anthropic',
        ]],
    ]);
});

it('drops a model entry with no id rather than listing one nothing can address', function () {
    $list = (new AnthropicUpstream)->normaliseModels(['data' => [['type' => 'model']]]);

    expect($list['data'])->toBe([]);
});

/*
 | Anthropic has no embeddings API. The point of this test is only that the
 | driver forwards the call rather than refusing it itself — Anthropic's own
 | 404 is what a caller actually sees.
 */
it('forwards an embeddings request to the same path an OpenAI-compatible upstream would use', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    (new AnthropicUpstream)->embeddings(anthropicGateway(), ['model' => 'm', 'input' => 'hi'], [
        'authorization' => 'Bearer sk-caller',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://anthropic.test/v1/embeddings'
        && $request->hasHeader('x-api-key', 'sk-caller')
        && $request['input'] === 'hi');
});

it('hands an embeddings answer back untouched', function () {
    $list = ['object' => 'list', 'data' => []];

    expect((new AnthropicUpstream)->normaliseEmbeddings($list))->toBe($list);
});

it('forwards a moderations request the same way', function () {
    Http::fake(['*' => Http::response(['results' => []])]);

    (new AnthropicUpstream)->moderations(anthropicGateway(), ['input' => 'hi'], ['authorization' => 'sk-caller']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://anthropic.test/v1/moderations'
        && $request->hasHeader('x-api-key', 'sk-caller'));
});

it('hands a moderation answer back untouched', function () {
    $result = ['results' => [['flagged' => false]]];

    expect((new AnthropicUpstream)->normaliseModerations($result))->toBe($result);
});

/*
 | Tools. Anthropic states one flat with the schema under `input_schema`,
 | carries a call as a `tool_use` content block rather than an array beside
 | the text, and has no tool role at all — a result is a block inside a user
 | message. All four differences are the driver's to hide.
 */

it('flattens a tool declaration into anthropic form', function () {
    sendToAnthropic(['model' => 'm', 'tools' => [[
        'type' => 'function',
        'function' => [
            'name' => 'count_invoices',
            'description' => 'How many invoices are archived.',
            'parameters' => ['type' => 'object', 'properties' => ['archive' => ['type' => 'string']]],
        ],
    ]]]);

    Http::assertSent(fn (Request $request) => $request['tools'] === [[
        'name' => 'count_invoices',
        'description' => 'How many invoices are archived.',
        'input_schema' => ['type' => 'object', 'properties' => ['archive' => ['type' => 'string']]],
    ]]);
});

/*
 | An empty PHP array encodes to `[]`, and Anthropic rejects a schema that is
 | not an object — so a tool taking no arguments declares one explicitly.
 */
it('gives a tool with no schema an empty object one', function () {
    sendToAnthropic(['model' => 'm', 'tools' => [['type' => 'function', 'function' => ['name' => 'now']]]]);

    Http::assertSent(fn (Request $request) => json_decode((string) $request->body(), true)['tools'][0]['input_schema']
        === ['type' => 'object', 'properties' => []]);
});

it('translates the tool_choice keywords', function (string|array $choice, array $expected) {
    sendToAnthropic([
        'model' => 'm',
        'tools' => [['type' => 'function', 'function' => ['name' => 'now']]],
        'tool_choice' => $choice,
    ]);

    Http::assertSent(fn (Request $request) => $request['tool_choice'] === $expected);
})->with([
    ['auto', ['type' => 'auto']],
    ['required', ['type' => 'any']],
    ['none', ['type' => 'none']],
    [['type' => 'function', 'function' => ['name' => 'now']], ['type' => 'tool', 'name' => 'now']],
]);

it('sends no tool_choice when the caller named none', function () {
    sendToAnthropic(['model' => 'm', 'tools' => [['type' => 'function', 'function' => ['name' => 'now']]]]);

    Http::assertSent(fn (Request $request) => ! array_key_exists('tool_choice', $request->data()));
});

it('carries an assistant tool call as a tool_use block', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?'],
        ['role' => 'assistant', 'content' => 'einen Moment', 'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ]]],
    ]]);

    // Read off the wire rather than out of `data()`: the input is cast to an
    // object so an empty one encodes as `{}`, and only the body shows that.
    Http::assertSent(fn (Request $request) => json_decode((string) $request->body(), true)['messages'][1] === [
        'role' => 'assistant',
        'content' => [
            ['type' => 'text', 'text' => 'einen Moment'],
            [
                'type' => 'tool_use',
                'id' => 'call_1',
                'name' => 'count_invoices',
                'input' => ['archive' => 'main'],
            ],
        ],
    ]);
});

it('encodes an empty tool input as an object, not an array', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'assistant', 'tool_calls' => [['id' => 'call_1', 'function' => ['name' => 'now']]]],
    ]]);

    Http::assertSent(fn (Request $request) => str_contains((string) $request->body(), '"input":{}'));
});

it('leaves out the text block when the assistant only asked for a tool', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [[
            'id' => 'call_1',
            'function' => ['name' => 'now', 'arguments' => '{}'],
        ]]],
    ]]);

    Http::assertSent(fn (Request $request) => count($request['messages'][0]['content']) === 1
        && $request['messages'][0]['content'][0]['type'] === 'tool_use');
});

it('turns a tool result into a tool_result block in a user message', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
    ]]);

    Http::assertSent(fn (Request $request) => $request['messages'] === [[
        'role' => 'user',
        'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => '42']],
    ]]);
});

/*
 | Anthropic requires the roles to alternate, so two results answering one
 | assistant turn are two blocks of a single user message, not two messages.
 */
it('merges consecutive tool results into one user message', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'assistant', 'tool_calls' => [
            ['id' => 'call_1', 'function' => ['name' => 'count_invoices', 'arguments' => '{}']],
            ['id' => 'call_2', 'function' => ['name' => 'list_archives', 'arguments' => '{}']],
        ]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
        ['role' => 'tool', 'tool_call_id' => 'call_2', 'content' => 'main'],
    ]]);

    Http::assertSent(fn (Request $request) => count($request['messages']) === 2
        && $request['messages'][1]['role'] === 'user'
        && count($request['messages'][1]['content']) === 2
        && $request['messages'][1]['content'][1]['tool_use_id'] === 'call_2');
});

it('does not append a tool result to a user turn of their own', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'hi']]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
    ]]);

    Http::assertSent(fn (Request $request) => count($request['messages']) === 2
        && $request['messages'][1]['content'][0]['type'] === 'tool_result');
});

it('normalises tool_use blocks into openai tool calls', function () {
    $completion = (new AnthropicUpstream)->normalise([
        'id' => 'msg_1',
        'stop_reason' => 'tool_use',
        'content' => [
            ['type' => 'text', 'text' => 'einen Moment'],
            [
                'type' => 'tool_use',
                'id' => 'toolu_1',
                'name' => 'count_invoices',
                'input' => ['archive' => 'main'],
            ],
        ],
    ]);

    expect($completion['choices'][0]['message']['content'])->toBe('einen Moment')
        ->and($completion['choices'][0]['message']['tool_calls'])->toBe([[
            'id' => 'toolu_1',
            'type' => 'function',
            'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ]])
        ->and($completion['choices'][0]['finish_reason'])->toBe('tool_calls');
});

it('reports no tool_calls key at all when none came back', function () {
    $message = (new AnthropicUpstream)->normalise([
        'content' => [['type' => 'text', 'text' => 'hi']],
    ])['choices'][0]['message'];

    expect($message)->not->toHaveKey('tool_calls');
});

/*
 | A tool this driver has no restatement for goes up as the caller wrote it,
 | the way every other untranslatable request in this app does — Anthropic's
 | own error is what tells the caller which side could not do the job.
 */

it('forwards a tool it cannot restate unchanged', function (array $tool) {
    sendToAnthropic(['model' => 'm', 'tools' => [$tool]]);

    Http::assertSent(fn (Request $request) => $request['tools'] === [$tool]);
})->with([
    [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3]],
    [['type' => 'text_editor_20250124', 'name' => 'str_replace_editor']],
    [['type' => 'function', 'function' => ['description' => 'no name']]],
]);

it('restates only the functions of a mixed tool list', function () {
    sendToAnthropic(['model' => 'm', 'tools' => [
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
        ['type' => 'function', 'function' => ['name' => 'count_invoices', 'parameters' => ['type' => 'object']]],
    ]]);

    Http::assertSent(fn (Request $request) => $request['tools'] === [
        ['type' => 'web_search_20250305', 'name' => 'web_search'],
        ['name' => 'count_invoices', 'input_schema' => ['type' => 'object']],
    ]);
});

/*
 | Anthropic has no `strict`; a field it would reject is dropped as part of
 | translating the tool, not as a capability the caller silently loses — the
 | model is still given the tool.
 */
it('leaves the strict flag behind, since anthropic has no such field', function () {
    sendToAnthropic(['model' => 'm', 'tools' => [
        ['type' => 'function', 'function' => ['name' => 'now', 'strict' => true]],
    ]]);

    Http::assertSent(fn (Request $request) => json_decode((string) $request->body(), true)['tools'][0] === [
        'name' => 'now',
        'input_schema' => ['type' => 'object', 'properties' => []],
    ]);
});

it('sends no tools key when the caller declared none', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

    Http::assertSent(fn (Request $request) => ! array_key_exists('tools', $request->data()));
});

/*
 | Edge cases of the conversation rewrite.
 */

it('states an empty input for a call whose arguments were not json', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'assistant', 'tool_calls' => [[
            'id' => 'call_1',
            'function' => ['name' => 'now', 'arguments' => '{"archive":'],
        ]]],
    ]]);

    Http::assertSent(fn (Request $request) => str_contains((string) $request->body(), '"input":{}'));
});

it('flattens a tool result the caller sent as content parts', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => [
            ['type' => 'text', 'text' => '42'],
        ]],
    ]]);

    Http::assertSent(fn (Request $request) => $request['messages'][0]['content'][0]['content'] === '42');
});

it('keeps a tool result whose id the caller left out', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'tool', 'content' => '42'],
    ]]);

    Http::assertSent(fn (Request $request) => $request['messages'][0]['content'][0] === [
        'type' => 'tool_result',
        'tool_use_id' => '',
        'content' => '42',
    ]);
});

/*
 | Results only merge into a message this driver opened. A user turn between
 | two of them ends the merge, or the second result would be appended to a
 | message that is making a different claim.
 */
it('stops merging results once a user turn came between them', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
        ['role' => 'user', 'content' => 'und im zweiten Archiv?'],
        ['role' => 'tool', 'tool_call_id' => 'call_2', 'content' => '7'],
    ]]);

    Http::assertSent(fn (Request $request) => count($request['messages']) === 3
        && $request['messages'][2]['content'][0]['tool_use_id'] === 'call_2');
});

it('carries several calls of one assistant turn as several tool_use blocks', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'assistant', 'tool_calls' => [
            ['id' => 'call_1', 'function' => ['name' => 'a', 'arguments' => '{}']],
            ['id' => 'call_2', 'function' => ['name' => 'b', 'arguments' => '{}']],
        ]],
    ]]);

    Http::assertSent(fn (Request $request) => count($request['messages'][0]['content']) === 2
        && $request['messages'][0]['content'][1]['id'] === 'call_2');
});

it('ignores a tool_calls key on a message that is not the assistant', function () {
    sendToAnthropic(['model' => 'm', 'messages' => [
        ['role' => 'user', 'content' => 'hi', 'tool_calls' => []],
    ]]);

    Http::assertSent(fn (Request $request) => $request['messages'][0]['content'] === 'hi');
});

it('normalises several tool_use blocks in order', function () {
    $calls = (new AnthropicUpstream)->normalise(['content' => [
        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'a', 'input' => []],
        ['type' => 'text', 'text' => 'between'],
        ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'b', 'input' => ['x' => 1]],
    ]])['choices'][0]['message']['tool_calls'];

    expect($calls)->toHaveCount(2)
        ->and($calls[0]['function']['arguments'])->toBe('{}')
        ->and($calls[1]['id'])->toBe('toolu_2')
        ->and($calls[1]['function']['arguments'])->toBe('{"x":1}');
});
