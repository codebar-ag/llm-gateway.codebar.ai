<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\GatewayRequest;
use App\Models\Gateway;
use App\Services\Gateways;
use Illuminate\Http\RedirectResponse;

class UpdateGatewayController
{
    public function __invoke(GatewayRequest $request, Gateway $gateway, Gateways $gateways): RedirectResponse
    {
        $gateway->update($request->validatedAttributes());

        /*
         | Promotion only, never demotion: the table must always hold exactly one
         | default, so clearing the box on the gateway that has it would leave
         | every request without an id nowhere to go. Moving the flag is done by
         | promoting a different gateway, which is why the form disables the box
         | on the one already holding it.
         */
        if ($request->boolean('is_default') && ! $gateway->is_default) {
            $gateways->makeDefault($gateway);
        }

        return redirect()
            ->route('gateways')
            ->with('status', "Gateway “{$gateway->name}” updated.");
    }
}
