<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Models\Gateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Everything the drivers do the same way.
 *
 * Each of the four calls is the same three steps — build the request, post or
 * get the gateway's URL for that call, throw on an upstream error so a queued
 * entry fails rather than storing a 500 as an answer. Only the reshaping
 * differs, so that is all a driver overrides: `body()` and `embeddingsBody()`
 * on the way up, the `normalise*()` methods on the way back. A driver that
 * translates nothing inherits the whole class and passes through, which is
 * why `OpenAiUpstream` has no body of its own.
 *
 * The defaults here are deliberately passthrough rather than abstract. An
 * upstream this app has no translation for should forward the caller's
 * request and let the upstream's own 404 answer it — inventing an error here
 * would hide which side could not do the job.
 */
abstract class Upstream implements UpstreamDriver
{
    /**
     * The one place a call to an upstream is configured.
     *
     * Redirects are refused rather than followed, so that the address an
     * operator reviewed is the address that gets called. Guzzle would follow up
     * to five, which makes `base_url` merely the first hop: an upstream
     * answering `302` decides the rest, and whatever comes back is stored on
     * the entry and rendered whole in the dashboard.
     *
     * Not a credential leak — Guzzle drops `Authorization` and `Cookie` when a
     * redirect crosses origins. It is a question of who chooses the destination.
     * A 3xx now reaches the caller as the upstream's own answer, which is the
     * honest report: this gateway talks to the endpoint it was configured with,
     * not to wherever that endpoint points next.
     *
     * @param  array<string, string>  $headers  the caller's forwarded headers
     */
    protected function request(Gateway $gateway, array $headers): PendingRequest
    {
        return Http::withHeaders($this->headers($headers))
            ->timeout($gateway->timeout)
            ->connectTimeout($gateway->connect_timeout)
            ->withOptions(['allow_redirects' => false]);
    }

    /**
     * The caller's headers travel as they arrived. A driver whose upstream
     * reads the credential from somewhere else overrides this — see
     * `AnthropicUpstream::headers()`.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function headers(array $headers): array
    {
        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function send(Gateway $gateway, array $payload, array $headers): Response
    {
        return $this->request($gateway, $headers)
            ->post($gateway->endpoint(), $this->body($payload))
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function body(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normalise(array $body): array
    {
        return $body;
    }

    /** @param  array<string, string>  $headers */
    public function models(Gateway $gateway, array $headers): Response
    {
        return $this->request($gateway, $headers)
            ->get($gateway->modelsEndpoint())
            ->throw();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normaliseModels(array $body): array
    {
        return $body;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function embeddings(Gateway $gateway, array $payload, array $headers): Response
    {
        return $this->request($gateway, $headers)
            ->post($gateway->embeddingsEndpoint(), $this->embeddingsBody($payload))
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function embeddingsBody(array $payload): array
    {
        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normaliseEmbeddings(array $body): array
    {
        return $body;
    }

    /**
     * No driver reshapes a moderation request, so there is no hook for it.
     * Neither Anthropic nor Ollama has a moderation endpoint of its own; the
     * payload goes up as the caller wrote it and the upstream's own 404 is
     * what tells the caller this gateway cannot answer it.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function moderations(Gateway $gateway, array $payload, array $headers): Response
    {
        return $this->request($gateway, $headers)
            ->post($gateway->moderationsEndpoint(), $payload)
            ->throw();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normaliseModerations(array $body): array
    {
        return $body;
    }

    /**
     * A count the upstream did not report stays null rather than being summed
     * as a zero, so "it did not say" keeps reading differently from "none".
     *
     * @return array<string, int|null>
     */
    protected static function usage(?int $promptTokens, ?int $completionTokens): array
    {
        $reported = array_filter([$promptTokens, $completionTokens], fn (?int $count) => $count !== null);

        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $reported === [] ? null : array_sum($reported),
        ];
    }

    /**
     * Upstreams date their models in ISO-8601 or RFC 3339; the rest of this
     * app deals in unix seconds. A missing or unparseable date becomes null
     * rather than the epoch, so "no date" does not read as 1970.
     */
    protected static function timestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
