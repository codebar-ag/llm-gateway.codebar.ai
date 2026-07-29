<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Models\Gateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The passthrough path: headers and body go up exactly as they arrived, and
 * the answer comes back exactly as it was given. LiteLLM, vLLM, OpenRouter,
 * Groq, Azure and Ollama's OpenAI shim all sit behind this.
 */
class OpenAiUpstream implements UpstreamDriver
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function send(Gateway $gateway, array $payload, array $headers): Response
    {
        return Http::withHeaders($headers)
            ->timeout($gateway->timeout)
            ->connectTimeout($gateway->connect_timeout)
            ->post($gateway->endpoint(), $payload)
            ->throw();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normalise(array $body): array
    {
        return $body;
    }
}
