<?php

namespace App\Http\Controllers\Auth;

use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SendEmailVerificationNotificationController
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->route('auth.login');
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended();
        }

        $request->user()->notify(new VerifyEmailNotification()->locale($request->user()->locale->value));

        flash(__('A new verification link has been sent to the email address you provided during registration.'), 'success');

        return back();
    }
}
