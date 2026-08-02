<?php

declare(strict_types=1);

use App\Support\ChatPayload;

it('extracts plain answer text', function () {
    expect(ChatPayload::text(['choices' => [['message' => ['content' => '  hello  ']]]]))->toBe('hello');
});

it('falls back to reasoning content when content is empty', function () {
    expect(ChatPayload::text(['choices' => [['message' => [
        'content' => '',
        'reasoning_content' => 'from reasoning',
    ]]]]))->toBe('from reasoning');
});

it('keeps only what follows the last think block', function () {
    expect(ChatPayload::text(['choices' => [['message' => [
        'content' => "<think>step one</think><think>step two</think>\nfinal answer",
    ]]]]))->toBe('final answer');
});

it('strips chat template tokens', function () {
    expect(ChatPayload::text(['choices' => [['message' => [
        'content' => '<|im_start|>answer<|im_end|>',
    ]]]]))->toBe('answer');
});

it('returns an empty string for malformed completions', function (array $completion) {
    expect(ChatPayload::text($completion))->toBe('');
})->with([
    [[]],
    [['choices' => []]],
    [['choices' => [['message' => []]]]],
    [['choices' => [['message' => ['content' => '   ']]]]],
]);

it('translates string input into a user message', function () {
    expect(ChatPayload::toMessages(['input' => 'hello']))->toBe([
        ['role' => 'user', 'content' => 'hello'],
    ]);
});

it('prepends instructions as a system message', function () {
    expect(ChatPayload::toMessages(['instructions' => 'Be brief.', 'input' => 'hi']))->toBe([
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'hi'],
    ]);
});

it('ignores blank instructions', function () {
    expect(ChatPayload::toMessages(['instructions' => '', 'input' => 'hi']))->toBe([
        ['role' => 'user', 'content' => 'hi'],
    ]);
});

it('translates mixed input items and defaults the role', function () {
    expect(ChatPayload::toMessages(['input' => [
        'plain string',
        ['content' => 'no role'],
        ['role' => 'assistant', 'content' => 'earlier answer'],
    ]]))->toBe([
        ['role' => 'user', 'content' => 'plain string'],
        ['role' => 'user', 'content' => 'no role'],
        ['role' => 'assistant', 'content' => 'earlier answer'],
    ]);
});

it('translates multimodal content and drops unknown parts', function () {
    expect(ChatPayload::toMessages(['input' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'input_text', 'text' => 'look'],
            ['type' => 'input_image', 'image_url' => 'data:image/png;base64,abc'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/x.png']],
            ['type' => 'input_audio', 'audio' => 'nope'],
        ],
    ]]]))->toBe([[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'look'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,abc']],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/x.png']],
        ],
    ]]);
});

it('returns no messages for empty payloads', function () {
    expect(ChatPayload::toMessages([]))->toBe([]);
});

/*
 | The second turn of a tool call. A caller who collected `function_call`
 | items from a poll sends them back beside the results they ran, and both
 | have to arrive upstream as the assistant/tool pair chat completions spells
 | the same turn with.
 */

it('translates a function call item into an assistant tool call', function () {
    expect(ChatPayload::toMessages(['input' => [
        ['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?'],
        [
            'type' => 'function_call',
            'call_id' => 'call_1',
            'name' => 'count_invoices',
            'arguments' => '{"archive":"main"}',
        ],
        ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '42'],
    ]]))->toBe([
        ['role' => 'user', 'content' => 'wie viele Rechnungen habe ich im Archiv?'],
        ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'count_invoices', 'arguments' => '{"archive":"main"}'],
        ]]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
    ]);
});

/*
 | A turn is one assistant message carrying N calls followed by N results.
 | Stating it as N assistant messages of one call each is what an upstream
 | rejects, so consecutive calls merge.
 */
it('merges consecutive function calls into one assistant turn', function () {
    $messages = ChatPayload::toMessages(['input' => [
        ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'count_invoices', 'arguments' => '{}'],
        ['type' => 'function_call', 'call_id' => 'call_2', 'name' => 'list_archives', 'arguments' => '{}'],
        ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '42'],
        ['type' => 'function_call_output', 'call_id' => 'call_2', 'output' => 'main'],
    ]]);

    expect($messages)->toHaveCount(3)
        ->and($messages[0]['tool_calls'])->toHaveCount(2)
        ->and($messages[1])->toBe(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'])
        ->and($messages[2])->toBe(['role' => 'tool', 'tool_call_id' => 'call_2', 'content' => 'main']);
});

it('starts a new assistant turn when a result came between the calls', function () {
    $messages = ChatPayload::toMessages(['input' => [
        ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'count_invoices', 'arguments' => '{}'],
        ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '42'],
        ['type' => 'function_call', 'call_id' => 'call_2', 'name' => 'list_archives', 'arguments' => '{}'],
    ]]);

    expect($messages)->toHaveCount(3)
        ->and($messages[2]['tool_calls'])->toHaveCount(1);
});

it('flattens a function call output stated as content parts', function () {
    expect(ChatPayload::toMessages(['input' => [[
        'type' => 'function_call_output',
        'call_id' => 'call_1',
        'output' => [['type' => 'output_text', 'text' => '42']],
    ]]]))->toBe([['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42']]);
});

/*
 | The drivers read tool messages straight off the stored conversation, so
 | nothing here may drop them on the way past.
 */
it('keeps tool messages and tool calls in the conversation', function () {
    $conversation = [
        ['role' => 'assistant', 'tool_calls' => [['id' => 'call_1', 'function' => ['name' => 'now']]]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '42'],
    ];

    expect(ChatPayload::conversation(['messages' => [
        ['role' => 'system', 'content' => 'Be brief.'],
        ...$conversation,
    ]]))->toBe($conversation);
});

/*
 | The three helpers the Anthropic and Ollama drivers lean on. Neither
 | upstream takes a system role inside the conversation the way OpenAI does,
 | and neither takes OpenAI's multimodal content parts.
 */

it('separates the conversation from the system prompt', function () {
    $payload = ['messages' => [
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'hi'],
        ['role' => 'assistant', 'content' => 'hello'],
    ]];

    expect(ChatPayload::conversation($payload))->toBe([
        ['role' => 'user', 'content' => 'hi'],
        ['role' => 'assistant', 'content' => 'hello'],
    ])->and(ChatPayload::systemPrompt($payload))->toBe('Be brief.');
});

it('joins several system messages into one prompt', function () {
    expect(ChatPayload::systemPrompt(['messages' => [
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'system', 'content' => 'Answer in German.'],
        ['role' => 'user', 'content' => 'hi'],
    ]]))->toBe("Be brief.\n\nAnswer in German.");
});

it('reports no system prompt when there is none', function () {
    expect(ChatPayload::systemPrompt(['messages' => [['role' => 'user', 'content' => 'hi']]]))->toBe('');
});

it('survives a payload with no messages at all', function () {
    expect(ChatPayload::conversation([]))->toBe([])
        ->and(ChatPayload::systemPrompt([]))->toBe('');
});

it('flattens content parts to their text', function () {
    expect(ChatPayload::contentText([
        ['type' => 'text', 'text' => 'first'],
        ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/x.png']],
        ['type' => 'text', 'text' => 'second'],
    ]))->toBe("first\nsecond");
});

it('leaves plain string content alone', function () {
    expect(ChatPayload::contentText('just text'))->toBe('just text');
});

/*
 | Edge cases of the tool turn. A caller assembles this input by hand from
 | what a poll handed them, so every part of it can be missing or out of the
 | order the happy path assumes.
 */

it('does not merge a tool call into an assistant message that carried text', function () {
    $messages = ChatPayload::toMessages(['input' => [
        ['role' => 'assistant', 'content' => 'einen Moment'],
        ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'now', 'arguments' => '{}'],
    ]]);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->not->toHaveKey('tool_calls')
        ->and($messages[1]['tool_calls'])->toHaveCount(1);
});

it('opens a turn with a function call that no message preceded', function () {
    $messages = ChatPayload::toMessages(['input' => [
        ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'now', 'arguments' => '{}'],
    ]]);

    expect($messages)->toBe([['role' => 'assistant', 'content' => null, 'tool_calls' => [[
        'id' => 'call_1',
        'type' => 'function',
        'function' => ['name' => 'now', 'arguments' => '{}'],
    ]]]]);
});

it('keeps a result whose call id the caller left out', function () {
    expect(ChatPayload::toMessages(['input' => [
        ['type' => 'function_call_output', 'output' => '42'],
    ]]))->toBe([['role' => 'tool', 'tool_call_id' => '', 'content' => '42']]);
});

it('states an empty result as empty rather than dropping the turn', function () {
    expect(ChatPayload::toMessages(['input' => [
        ['type' => 'function_call_output', 'call_id' => 'call_1'],
    ]]))->toBe([['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '']]);
});

it('carries instructions in front of a conversation that opens with a tool call', function () {
    $messages = ChatPayload::toMessages([
        'instructions' => 'Be brief.',
        'input' => [['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'now']],
    ]);

    expect($messages[0])->toBe(['role' => 'system', 'content' => 'Be brief.'])
        ->and($messages[1]['role'])->toBe('assistant');
});

it('reads a function call whose arguments the caller sent as an object', function () {
    expect(ChatPayload::toMessages(['input' => [[
        'type' => 'function_call',
        'call_id' => 'call_1',
        'name' => 'count_invoices',
        'arguments' => ['archive' => 'main'],
    ]]])[0]['tool_calls'][0]['function']['arguments'])->toBe('{"archive":"main"}');
});

/*
 | An unknown item type is a message as far as this translation is concerned.
 | Guessing at it would be worse than handing the upstream something it can
 | reject by name.
 */
it('treats an item of an unknown type as a message', function () {
    expect(ChatPayload::toMessages(['input' => [
        ['type' => 'reasoning', 'role' => 'assistant', 'content' => 'thinking'],
    ]]))->toBe([['role' => 'assistant', 'content' => 'thinking']]);
});
