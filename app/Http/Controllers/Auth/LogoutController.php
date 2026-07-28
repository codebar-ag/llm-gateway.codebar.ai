<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $guards = array_keys(Config::array('auth.guards'));

        foreach ($guards as $guard) {
            $guardInstance = app('auth')->guard((string) $guard);

            if ($guardInstance instanceof StatefulGuard) {
                $guardInstance->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->intended();
    }
}
