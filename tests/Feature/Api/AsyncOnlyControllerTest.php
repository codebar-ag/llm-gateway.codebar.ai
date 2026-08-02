<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('answers unknown v1 paths with an async-only hint', function (string $method, string $path) {
    $this->json($method, $path, [], gatewayAuth())
        ->assertStatus(404)
        ->assertJsonPath('error.type', 'async_only');
})->with([
    ['POST', '/v1/files'],
    ['PUT', '/v1/some/deep/path'],
    ['DELETE', '/v1/files/abc'],
    ['PATCH', '/v1/anything'],
]);

it('does not shadow the real endpoints', function () {
    $this->getJson('/v1/responses', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list');
});

it('does not shadow the model endpoints, which are the only synchronous ones left', function () {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->getJson('/v1/models', gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list');
});

it('does not shadow the endpoints that queue', function (string $path, string $object) {
    // The queue runs inline under QUEUE_CONNECTION=sync; faking it keeps this
    // about routing rather than about what a worker would then do.
    Queue::fake();

    $this->postJson($path, ['model' => 'm', 'input' => 'hi'], gatewayAuth())
        ->assertStatus(202)
        ->assertJsonPath('object', $object);
})->with([
    ['/v1/embeddings', 'embedding.queued'],
    ['/v1/moderations', 'moderation.queued'],
]);
