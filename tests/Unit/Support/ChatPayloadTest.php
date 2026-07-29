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
