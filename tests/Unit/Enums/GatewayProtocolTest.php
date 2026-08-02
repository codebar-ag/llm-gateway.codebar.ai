<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;

it('covers every protocol with a label, tone and path', function (GatewayProtocol $protocol) {
    expect($protocol->label())->not->toBeEmpty()
        ->and($protocol->tone())->not->toBeEmpty()
        ->and($protocol->defaultPath())->toStartWith('/')
        ->and($protocol->modelsPath())->toStartWith('/')
        ->and($protocol->embeddingsPath())->toStartWith('/')
        ->and($protocol->moderationsPath())->toStartWith('/');
})->with(GatewayProtocol::cases());

it('keeps the stored values stable', function () {
    expect(array_column(GatewayProtocol::cases(), 'value'))
        ->toBe(['openai', 'anthropic', 'ollama']);
});

it('sends each protocol to its own endpoint', function () {
    expect(GatewayProtocol::OpenAi->defaultPath())->toBe('/v1/chat/completions')
        ->and(GatewayProtocol::Anthropic->defaultPath())->toBe('/v1/messages')
        ->and(GatewayProtocol::Ollama->defaultPath())->toBe('/api/chat');
});

/*
 | Only the OpenAI-compatible path is a true passthrough. The dashboard says so
 | on the entry page, so a reader is never left thinking the stored body is
 | what an Anthropic or Ollama upstream actually received.
 */
it('reports only openai as a passthrough', function () {
    expect(GatewayProtocol::OpenAi->isPassthrough())->toBeTrue()
        ->and(GatewayProtocol::Anthropic->isPassthrough())->toBeFalse()
        ->and(GatewayProtocol::Ollama->isPassthrough())->toBeFalse();
});

/*
 | Ollama has no OpenAI-shaped listing of its own; its native /api/tags is
 | what a driver reads and then normalises.
 */
it('lists models from a path of its own', function () {
    expect(GatewayProtocol::OpenAi->modelsPath())->toBe('/v1/models')
        ->and(GatewayProtocol::Anthropic->modelsPath())->toBe('/v1/models')
        ->and(GatewayProtocol::Ollama->modelsPath())->toBe('/api/tags');
});

/*
 | Ollama's native /api/embed takes the same model/input shape OpenAI's
 | embeddings request does; Anthropic has no embeddings API at all, so its
 | path only exists to give a driver somewhere to send a request that
 | Anthropic itself will answer with a 404.
 */
it('creates embeddings from a path of its own', function () {
    expect(GatewayProtocol::OpenAi->embeddingsPath())->toBe('/v1/embeddings')
        ->and(GatewayProtocol::Anthropic->embeddingsPath())->toBe('/v1/embeddings')
        ->and(GatewayProtocol::Ollama->embeddingsPath())->toBe('/api/embed');
});

/*
 | No protocol here has ever implemented moderations; every gateway is
 | pointed at the OpenAI-shaped path and lets the upstream's own answer (or
 | lack of one) speak for itself.
 */
it('sends every protocol to the same moderations path', function () {
    expect(GatewayProtocol::OpenAi->moderationsPath())->toBe('/v1/moderations')
        ->and(GatewayProtocol::Anthropic->moderationsPath())->toBe('/v1/moderations')
        ->and(GatewayProtocol::Ollama->moderationsPath())->toBe('/v1/moderations');
});
