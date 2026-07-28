<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;

final class ShowLoginController
{
    public function __invoke(): View
    {
        /** @var array<string, mixed> $configProviders */
        $configProviders = config('default.auth.providers', []);
        $providers = collect($configProviders);

        return view('auth.login')->with('providers', $providers);
    }
}
