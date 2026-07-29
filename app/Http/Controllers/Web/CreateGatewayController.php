<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;
use App\Support\GatewayPayload;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class CreateGatewayController
{
    public function __invoke(): Response
    {
        $gateway = new Gateway([
            'protocol' => GatewayProtocol::OpenAi,
            'timeout' => Config::integer('gateway.upstream.max_timeout'),
            'connect_timeout' => Config::integer('gateway.upstream.connect_timeout'),
            'is_active' => true,
        ]);

        return Inertia::render('Gateways/Form', [
            'gateway' => GatewayPayload::form($gateway),
            'protocols' => GatewayPayload::protocols(),
            'maxTimeout' => Config::integer('gateway.upstream.max_timeout'),
            'editing' => false,
            'urls' => [
                'submit' => route('gateways.store'),
                'index' => route('gateways'),
            ],
        ]);
    }
}
