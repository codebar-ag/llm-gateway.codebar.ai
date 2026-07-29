<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;

/**
 * Which driver speaks a given gateway's protocol.
 *
 * The match is exhaustive over GatewayProtocol, so adding a case to the enum
 * is a compile-time reminder to add the driver rather than a runtime surprise
 * in a worker.
 */
class Upstreams
{
    public function for(Gateway $gateway): UpstreamDriver
    {
        return match ($gateway->protocol) {
            GatewayProtocol::OpenAi => new OpenAiUpstream,
            GatewayProtocol::Anthropic => new AnthropicUpstream,
            GatewayProtocol::Ollama => new OllamaUpstream,
        };
    }
}
