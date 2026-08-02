<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

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

it('does not shadow the synchronous endpoints', function (string $method, string $path) {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->json($method, $path, ['model' => 'm'], gatewayAuth())
        ->assertOk()
        ->assertJsonPath('object', 'list');
})->with([
    ['GET', '/v1/models'],
    ['POST', '/v1/embeddings'],
    ['POST', '/v1/moderations'],
]);
