<?php

declare(strict_types=1);

use App\Models\Gateway;
use App\Services\Upstream\OllamaUpstream;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function sendToOllama(array $payload, array $headers = []): void
{
    Http::fake(['*' => Http::response(['message' => ['role' => 'assistant', 'content' => 'hi'], 'done' => true])]);

    (new OllamaUpstream)->send(
        Gateway::factory()->ollama()->make(['base_url' => 'http://ollama.test:11434']),
        $payload,
        $headers,
    );
}

it('posts to the native chat endpoint with streaming off', function () {
    sendToOllama(['model' => 'llama3.2:3b', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ollama.test:11434/api/chat'
        && $request['model'] === 'llama3.2:3b'
        && $request['stream'] === false);
});

/*
 | Ollama ignores an Authorization header, but one exposed through an
 | authenticating reverse proxy does not — so the caller's headers travel
 | as they do everywhere else in this app.
 */
it('forwards the caller headers unchanged', function () {
    sendToOllama(['model' => 'm'], ['authorization' => 'Bearer sk-caller']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-caller'));
});

it('keeps the system prompt as a leading system message', function () {
    sendToOllama(['model' => 'm', 'messages' => [
        ['role' => 'user', 'content' => 'hi'],
        ['role' => 'system', 'content' => 'Be brief.'],
    ]]);

    Http::assertSent(fn (Request $request) => $request['messages'] === [
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'hi'],
    ]);
});

it('flattens multimodal content to its text', function () {
    sendToOllama(['model' => 'm', 'messages' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'look'],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/x.png']],
        ],
    ]]]);

    Http::assertSent(fn (Request $request) => $request['messages'] === [
        ['role' => 'user', 'content' => 'look'],
    ]);
});

it('maps sampling parameters into options', function () {
    sendToOllama(['model' => 'm', 'temperature' => 0.2, 'top_p' => 0.9, 'max_tokens' => 128]);

    Http::assertSent(fn (Request $request) => $request['options'] === [
        'temperature' => 0.2,
        'top_p' => 0.9,
        'num_predict' => 128,
    ]);
});

it('sends no options when the caller set none', function () {
    sendToOllama(['model' => 'm', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

    Http::assertSent(fn (Request $request) => $request->data() === [
        'model' => 'm',
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'stream' => false,
    ]);
});

it('normalises an answer into a chat completion', function () {
    $completion = (new OllamaUpstream)->normalise([
        'model' => 'llama3.2:3b',
        'created_at' => '2026-07-29T05:00:00Z',
        'message' => ['role' => 'assistant', 'content' => 'the answer'],
        'done' => true,
        'done_reason' => 'stop',
        'prompt_eval_count' => 12,
        'eval_count' => 3,
    ]);

    expect($completion['object'])->toBe('chat.completion')
        ->and($completion['model'])->toBe('llama3.2:3b')
        ->and($completion['choices'][0]['message'])->toBe(['role' => 'assistant', 'content' => 'the answer'])
        ->and($completion['choices'][0]['finish_reason'])->toBe('stop')
        ->and($completion['usage'])->toBe([
            'prompt_tokens' => 12,
            'completion_tokens' => 3,
            'total_tokens' => 15,
        ]);
});

it('carries a thinking model answer through as reasoning content', function () {
    $completion = (new OllamaUpstream)->normalise([
        'message' => ['role' => 'assistant', 'content' => '', 'thinking' => 'worked it out'],
    ]);

    expect($completion['choices'][0]['message'])->toBe([
        'role' => 'assistant',
        'reasoning_content' => 'worked it out',
    ]);
});

it('maps done reasons onto finish reasons', function (string $doneReason, ?string $expected) {
    $completion = (new OllamaUpstream)->normalise(['done_reason' => $doneReason]);

    expect($completion['choices'][0]['finish_reason'])->toBe($expected);
})->with([
    ['stop', 'stop'],
    ['length', 'length'],
    ['load', null],
]);

it('reports unknown token counts as null rather than zero', function () {
    $completion = (new OllamaUpstream)->normalise(['message' => ['content' => 'hi']]);

    expect($completion['usage'])->toBe([
        'prompt_tokens' => null,
        'completion_tokens' => null,
        'total_tokens' => null,
    ]);
});

/* Absent counts stay unknown rather than being reported as a confident zero. */

it('reports a total of null when the answer carries no counts at all', function () {
    $usage = (new OllamaUpstream)->normalise(['model' => 'llama3.2:3b'])['usage'];

    expect($usage['prompt_tokens'])->toBeNull()
        ->and($usage['completion_tokens'])->toBeNull()
        ->and($usage['total_tokens'])->toBeNull();
});

it('still totals when only one of the two counts is reported', function () {
    expect((new OllamaUpstream)->normalise(['eval_count' => 5])['usage']['total_tokens'])->toBe(5);
});

it('lists models from the native tags endpoint', function () {
    Http::fake(['http://ollama.test:11434/api/tags' => Http::response(['models' => []])]);

    (new OllamaUpstream)->models(
        Gateway::factory()->ollama()->make(['base_url' => 'http://ollama.test:11434']),
        ['authorization' => 'Bearer sk-caller'],
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://ollama.test:11434/api/tags'
        && $request->hasHeader('Authorization', 'Bearer sk-caller'));
});

it('normalises a tags list into the OpenAI shape', function () {
    $list = (new OllamaUpstream)->normaliseModels([
        'models' => [
            ['name' => 'llama3.2:3b', 'model' => 'llama3.2:3b', 'modified_at' => '2026-07-29T05:00:00Z'],
        ],
    ]);

    expect($list)->toBe([
        'object' => 'list',
        'data' => [[
            'id' => 'llama3.2:3b',
            'object' => 'model',
            'created' => strtotime('2026-07-29T05:00:00Z'),
            'owned_by' => 'ollama',
        ]],
    ]);
});

it('falls back to the model digest name when a tag has no name', function () {
    $list = (new OllamaUpstream)->normaliseModels(['models' => [['model' => 'llama3.2:3b']]]);

    expect($list['data'][0]['id'])->toBe('llama3.2:3b');
});

it('posts to the native embed endpoint with the openai-shaped fields', function () {
    Http::fake(['http://ollama.test:11434/api/embed' => Http::response(['embeddings' => []])]);

    (new OllamaUpstream)->embeddings(
        Gateway::factory()->ollama()->make(['base_url' => 'http://ollama.test:11434']),
        ['model' => 'nomic-embed-text', 'input' => ['hi', 'there']],
        ['authorization' => 'Bearer sk-caller'],
    );

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ollama.test:11434/api/embed'
        && $request->hasHeader('Authorization', 'Bearer sk-caller')
        && $request['model'] === 'nomic-embed-text'
        && $request['input'] === ['hi', 'there']);
});

it('normalises an embed answer into the OpenAI embeddings shape', function () {
    $list = (new OllamaUpstream)->normaliseEmbeddings([
        'model' => 'nomic-embed-text',
        'embeddings' => [[0.1, 0.2], [0.3, 0.4]],
        'prompt_eval_count' => 6,
    ]);

    expect($list)->toBe([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'embedding' => [0.1, 0.2], 'index' => 0],
            ['object' => 'embedding', 'embedding' => [0.3, 0.4], 'index' => 1],
        ],
        'model' => 'nomic-embed-text',
        'usage' => ['prompt_tokens' => 6, 'total_tokens' => 6],
    ]);
});

it('reports no usage when ollama did not say', function () {
    $usage = (new OllamaUpstream)->normaliseEmbeddings(['embeddings' => []])['usage'];

    expect($usage)->toBe(['prompt_tokens' => null, 'total_tokens' => null]);
});

/*
 | Ollama has no moderation model of its own. The point of this test is only
 | that the driver forwards the call rather than refusing it itself —
 | Ollama's own 404 is what a caller actually sees.
 */
it('forwards a moderations request to the openai-shaped path', function () {
    Http::fake(['*' => Http::response(['results' => []])]);

    (new OllamaUpstream)->moderations(
        Gateway::factory()->ollama()->make(['base_url' => 'http://ollama.test:11434']),
        ['input' => 'hi'],
        ['authorization' => 'Bearer sk-caller'],
    );

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ollama.test:11434/v1/moderations'
        && $request->hasHeader('Authorization', 'Bearer sk-caller'));
});

it('hands a moderation answer back untouched', function () {
    $result = ['results' => [['flagged' => false]]];

    expect((new OllamaUpstream)->normaliseModerations($result))->toBe($result);
});
