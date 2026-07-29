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
}
