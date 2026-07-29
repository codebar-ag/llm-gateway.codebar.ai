<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Gateway;
use App\Services\Gateways;
use Illuminate\Http\RedirectResponse;

class MakeGatewayDefaultController
{
    public function __invoke(Gateway $gateway, Gateways $gateways): RedirectResponse
    {
        $gateways->makeDefault($gateway);

        return redirect()
            ->route('gateways')
            ->with('status', "Requests without a gateway id now go to “{$gateway->name}”.");
    }
}
