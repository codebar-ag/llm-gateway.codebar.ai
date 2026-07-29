<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\GatewayRequest;
use App\Models\Gateway;
use Illuminate\Http\RedirectResponse;

class UpdateGatewayController
{
    public function __invoke(GatewayRequest $request, Gateway $gateway): RedirectResponse
    {
        $gateway->update($request->validatedAttributes());

        return redirect()
            ->route('gateways')
            ->with('status', "Gateway “{$gateway->name}” updated.");
    }
}
