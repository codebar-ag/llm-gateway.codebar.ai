<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Gateway;
use App\Support\GatewayPayload;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class EditGatewayController
{
    public function __invoke(Gateway $gateway): Response
    {
        return Inertia::render('Gateways/Form', [
            'gateway' => GatewayPayload::form($gateway),
            'protocols' => GatewayPayload::protocols(),
            'maxTimeout' => Config::integer('gateway.upstream.max_timeout'),
            'editing' => true,
            'urls' => [
                'submit' => route('gateways.update', $gateway),
                'index' => route('gateways'),
            ],
        ]);
    }
}
