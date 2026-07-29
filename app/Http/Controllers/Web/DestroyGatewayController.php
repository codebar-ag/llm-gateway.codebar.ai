<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\CannotRetireDefaultGateway;
use App\Models\Gateway;
use App\Services\Gateways;
use Illuminate\Http\RedirectResponse;

class DestroyGatewayController
{
    public function __invoke(Gateway $gateway, Gateways $gateways): RedirectResponse
    {
        try {
            $gateways->delete($gateway);
        } catch (CannotRetireDefaultGateway $e) {
            return redirect()->route('gateways')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gateways')
            ->with('status', "Gateway “{$gateway->name}” retired. The entries it answered keep pointing at it.");
    }
}
