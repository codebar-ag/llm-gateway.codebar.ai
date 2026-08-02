<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The wire format an upstream speaks.
 *
 * Everything this app stores and returns is OpenAI chat-completion shaped;
 * a protocol is the translation a driver does at the edge, not a different
 * kind of request.
 */
enum GatewayProtocol: string
{
    /** LiteLLM, vLLM, OpenRouter, Groq, Azure — anything with /v1/chat/completions. */
    case OpenAi = 'openai';

    /** Anthropic's own Messages API. */
    case Anthropic = 'anthropic';

    /** Ollama's native /api/chat, as opposed to its OpenAI shim. */
    case Ollama = 'ollama';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI compatible',
            self::Anthropic => 'Anthropic',
            self::Ollama => 'Ollama',
        };
    }

    /** One of the design system's semantic tones, for Badge/StatusBadge. */
    public function tone(): string
    {
        return match ($this) {
            self::OpenAi => 'success',
            self::Anthropic => 'warning',
            self::Ollama => 'info',
        };
    }

    /** Path appended to the gateway's base URL unless it overrides it. */
    public function defaultPath(): string
    {
        return match ($this) {
            self::OpenAi => '/v1/chat/completions',
            self::Anthropic => '/v1/messages',
            self::Ollama => '/api/chat',
        };
    }

    /**
     * Whether the caller's body reaches this upstream untouched.
     *
     * Only true for OpenAI-compatible upstreams: the other two are stored in
     * one shape and spoken in another, so a driver has to rewrite them.
     */
    public function isPassthrough(): bool
    {
        return $this === self::OpenAi;
    }

    /** Where a gateway's model list is read from. */
    public function modelsPath(): string
    {
        return match ($this) {
            self::OpenAi, self::Anthropic => '/v1/models',
            self::Ollama => '/api/tags',
        };
    }

    /**
     * Where embeddings are created.
     *
     * Anthropic has no embeddings API of its own — the path is still named so
     * the driver has somewhere to send the request, and the upstream's own
     * 404 is what tells a caller that.
     */
    public function embeddingsPath(): string
    {
        return match ($this) {
            self::OpenAi, self::Anthropic => '/v1/embeddings',
            self::Ollama => '/api/embed',
        };
    }

    /**
     * Where moderations are run.
     *
     * Only OpenAI-compatible upstreams have ever implemented this endpoint;
     * Anthropic and Ollama are pointed at the same path an OpenAI-compatible
     * one would use, and answer with their own 404 rather than a guess this
     * app would have to invent.
     */
    public function moderationsPath(): string
    {
        return '/v1/moderations';
    }
}
