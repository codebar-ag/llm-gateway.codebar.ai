<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;

class StoreSessionController
{
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Against session fixation: the id a visitor arrived holding must not
        // be the id that now carries an authenticated session.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
