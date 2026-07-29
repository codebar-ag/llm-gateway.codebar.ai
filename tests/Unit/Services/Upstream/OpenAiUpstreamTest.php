<?php

declare(strict_types=1);

use App\Models\Gateway;
use App\Services\Upstream\OpenAiUpstream;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function openAiGateway(array $attributes = []): Gateway
{
    return Gateway::factory()->make(['base_url' => 'http://llm.test', ...$attributes]);
}

it('replays the payload and headers it was handed', function () {
    Http::fake(['http://llm.test/v1/chat/completions' => Http::response(['ok' => true])]);

    (new OpenAiUpstream)->send(openAiGateway(), ['model' => 'm'], [
        'authorization' => 'Bearer sk-caller',
        'x-tag' => 'invoices',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://llm.test/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-caller')
        && $request->hasHeader('X-Tag', 'invoices')
        && $request['model'] === 'm');
});

it('sends no headers of its own when handed none', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);

    (new OpenAiUpstream)->send(openAiGateway(), ['model' => 'm'], []);

    Http::assertSent(fn (Request $request) => $request->header('Authorization') === []);
});

it('trims a trailing slash from the gateway url', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);

    (new OpenAiUpstream)->send(openAiGateway(['base_url' => 'http://llm.test/']), ['model' => 'm'], []);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://llm.test/v1/chat/completions');
});

it('honours a path override', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);

    (new OpenAiUpstream)->send(
        openAiGateway(['path' => '/openai/deployments/gpt/chat/completions']),
        ['model' => 'm'],
        [],
    );

    Http::assertSent(fn (Request $request) => $request->url() === 'http://llm.test/openai/deployments/gpt/chat/completions');
});

it('throws on an upstream error so the queue can fail the entry', function () {
    Http::fake(['*' => Http::response(['error' => 'nope'], 500)]);

    expect(fn () => (new OpenAiUpstream)->send(openAiGateway(), ['model' => 'm'], []))
        ->toThrow(RequestException::class);
});

it('returns the response so the caller can read the raw body', function () {
    Http::fake(['*' => Http::response('not json at all', 200)]);

    expect((new OpenAiUpstream)->send(openAiGateway(), ['model' => 'm'], [])->body())
        ->toBe('not json at all');
});

it('hands the answer back untouched', function () {
    $completion = ['id' => 'chatcmpl-1', 'choices' => [['message' => ['content' => 'hi']]]];

    expect((new OpenAiUpstream)->normalise($completion))->toBe($completion);
});
