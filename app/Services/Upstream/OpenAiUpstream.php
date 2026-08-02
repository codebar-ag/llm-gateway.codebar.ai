<?php

declare(strict_types=1);

namespace App\Services\Upstream;

/**
 * The passthrough path: headers and body go up exactly as they arrived, and
 * the answer comes back exactly as it was given. LiteLLM, vLLM, OpenRouter,
 * Groq, Azure and Ollama's OpenAI shim all sit behind this.
 *
 * Nothing is overridden because nothing is translated — `Upstream`'s defaults
 * already are the OpenAI wire format this app speaks internally.
 */
class OpenAiUpstream extends Upstream {}
