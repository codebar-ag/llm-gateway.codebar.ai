<?php

declare(strict_types=1);

use App\Models\Gateway;
use App\Services\Upstream\OpenAiUpstream;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

/*
 | Every upstream call carries the caller's credential, and a followed
 | redirect would replay it at whatever host the upstream named. The 3xx is
 | handed back as the upstream's own answer instead of being chased.
 */
it('does not follow a redirect the upstream answers with', function () {
    Http::fake([
        'http://llm.test/*' => Http::response('', 302, ['Location' => 'http://elsewhere.test/v1/chat/completions']),
        'http://elsewhere.test/*' => Http::response(['ok' => true]),
    ]);

    $response = (new OpenAiUpstream)->send(openAiGateway(), ['model' => 'm'], ['authorization' => 'Bearer sk-caller']);

    expect($response->status())->toBe(302);

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request) => Str::startsWith($request->url(), 'http://elsewhere.test'));
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

it('lists models from the models endpoint', function () {
    Http::fake(['http://llm.test/v1/models' => Http::response(['object' => 'list', 'data' => []])]);

    (new OpenAiUpstream)->models(openAiGateway(), ['authorization' => 'Bearer sk-caller']);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'http://llm.test/v1/models'
        && $request->hasHeader('Authorization', 'Bearer sk-caller'));
});

it('hands the models list back untouched', function () {
    $list = ['object' => 'list', 'data' => [['id' => 'gpt-4', 'object' => 'model']]];

    expect((new OpenAiUpstream)->normaliseModels($list))->toBe($list);
});

it('posts to the embeddings endpoint', function () {
    Http::fake(['http://llm.test/v1/embeddings' => Http::response(['object' => 'list', 'data' => []])]);

    (new OpenAiUpstream)->embeddings(openAiGateway(), ['model' => 'm', 'input' => 'hi'], [
        'authorization' => 'Bearer sk-caller',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://llm.test/v1/embeddings'
        && $request->hasHeader('Authorization', 'Bearer sk-caller')
        && $request['input'] === 'hi');
});

it('hands the embeddings answer back untouched', function () {
    $list = ['object' => 'list', 'data' => [['object' => 'embedding', 'embedding' => [0.1], 'index' => 0]]];

    expect((new OpenAiUpstream)->normaliseEmbeddings($list))->toBe($list);
});

it('posts to the moderations endpoint', function () {
    Http::fake(['http://llm.test/v1/moderations' => Http::response(['results' => []])]);

    (new OpenAiUpstream)->moderations(openAiGateway(), ['input' => 'hi'], []);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://llm.test/v1/moderations'
        && $request['input'] === 'hi');
});

it('hands the moderation answer back untouched', function () {
    $result = ['id' => 'modr-1', 'results' => [['flagged' => false]]];

    expect((new OpenAiUpstream)->normaliseModerations($result))->toBe($result);
});
