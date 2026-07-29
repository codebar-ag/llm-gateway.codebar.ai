<?php

declare(strict_types=1);

use App\Enums\GatewayProtocol;

it('covers every protocol with a label, tone and path', function (GatewayProtocol $protocol) {
    expect($protocol->label())->not->toBeEmpty()
        ->and($protocol->tone())->not->toBeEmpty()
        ->and($protocol->defaultPath())->toStartWith('/');
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
