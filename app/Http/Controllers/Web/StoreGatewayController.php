<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\GatewayRequest;
use App\Services\Gateways;
use Illuminate\Http\RedirectResponse;

class StoreGatewayController
{
    public function __invoke(GatewayRequest $request, Gateways $gateways): RedirectResponse
    {
        $gateway = $gateways->create($request->validatedAttributes());

        // `create()` already promotes the very first gateway, so only ask for
        // the flag when it is not already held.
        if ($request->boolean('is_default') && ! $gateway->is_default) {
            $gateways->makeDefault($gateway);
        }

        return redirect()
            ->route('gateways')
            ->with('status', "Gateway “{$gateway->name}” created.");
    }
}
