<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DestroySessionController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();

        // Both, and in this order: `invalidate` drops the session data,
        // `regenerateToken` makes the CSRF token the logged-out page carries
        // useless to anyone who kept a copy of it.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
