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
