<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShowEmailVerificationNoticeController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('auth.login');
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended();
        }

        return view('auth.verify-email');
    }
}
