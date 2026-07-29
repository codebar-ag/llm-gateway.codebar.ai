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

    /** Which of the badge component's tones this protocol reads as. */
    public function tone(): string
    {
        return match ($this) {
            self::OpenAi => 'emerald',
            self::Anthropic => 'amber',
            self::Ollama => 'blue',
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
}
