<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;

class EditGatewayController
{
    public function __invoke(Gateway $gateway): View
    {
        return view('gateways.form', [
            'gateway' => $gateway,
            'protocols' => GatewayProtocol::cases(),
            'maxTimeout' => Config::integer('gateway.upstream.max_timeout'),
        ]);
    }
}
