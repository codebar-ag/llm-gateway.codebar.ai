<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;

class CreateGatewayController
{
    public function __invoke(): View
    {
        return view('gateways.form', [
            'gateway' => new Gateway([
                'protocol' => GatewayProtocol::OpenAi,
                'timeout' => Config::integer('gateway.upstream.max_timeout'),
                'connect_timeout' => Config::integer('gateway.upstream.connect_timeout'),
                'is_active' => true,
            ]),
            'protocols' => GatewayProtocol::cases(),
            'maxTimeout' => Config::integer('gateway.upstream.max_timeout'),
        ]);
    }
}
