<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Models\Gateway;
use Illuminate\Http\Client\Response;

/**
 * How one wire format is spoken.
 *
 * Split in two on purpose. `send()` owns everything protocol-specific about
 * the outgoing call; `normalise()` turns whatever came back into the OpenAI
 * chat-completion shape the rest of the app — storage, the Responses
 * resource, the dashboard — already reads. A non-JSON body never reaches
 * `normalise()`, so the worker can still store it raw.
 *
 * `Upstream` implements all of this once; a driver extends it and overrides
 * only the parts its wire format actually differs on.
 */
interface UpstreamDriver
{
    /**
     * Send the request upstream, throwing on any upstream error so the queue
     * turns it into a failed job.
     *
     * @param  array<string, mixed>  $payload  the stored chat-completion request
     * @param  array<string, string>  $headers  the caller's forwarded headers
     */
    public function send(Gateway $gateway, array $payload, array $headers): Response;

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed> an OpenAI chat completion
     */
    public function normalise(array $body): array;

    /**
     * List the models a gateway currently serves. Called synchronously, not
     * queued: it answers before this app has decided what to do with a
     * request, so there is nothing to hand a worker.
     *
     * @param  array<string, string>  $headers  the caller's forwarded headers
     */
    public function models(Gateway $gateway, array $headers): Response;

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed> an OpenAI models list
     */
    public function normaliseModels(array $body): array;

    /**
     * Create embeddings. Also synchronous — see `models()`. A caller sending
     * an embedding request expects vectors back, not an id to poll.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function embeddings(Gateway $gateway, array $payload, array $headers): Response;

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed> an OpenAI embeddings list
     */
    public function normaliseEmbeddings(array $body): array;

    /**
     * Run a moderation check. Also synchronous — see `models()`.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function moderations(Gateway $gateway, array $payload, array $headers): Response;

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed> an OpenAI moderation result
     */
    public function normaliseModerations(array $body): array;
}
