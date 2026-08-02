<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\CannotDeactivateDefaultGateway;
use App\Http\Requests\GatewayRequest;
use App\Models\Gateway;
use App\Services\Gateways;
use Illuminate\Http\RedirectResponse;

class UpdateGatewayController
{
    public function __invoke(GatewayRequest $request, Gateway $gateway, Gateways $gateways): RedirectResponse
    {
        try {
            $gateways->update($gateway, $request->validatedAttributes(), $request->boolean('is_default'));
        } catch (CannotDeactivateDefaultGateway $e) {
            /*
             | Back to the form rather than on to the index, the way a failed
             | validation rule would go: the operator has an edit in progress and
             | this refuses one field of it, which is not a reason to throw the
             | rest away. The message lands in the form's own error summary.
             */
            return back()->withInput()->withErrors(['is_active' => $e->getMessage()]);
        }

        return redirect()
            ->route('gateways')
            ->with('status', "Gateway “{$gateway->name}” updated.");
    }
}
