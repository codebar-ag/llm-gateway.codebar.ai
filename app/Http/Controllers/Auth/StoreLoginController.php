<?php

namespace App\Http\Controllers\Auth;

use App\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class StoreLoginController
{
    public function __invoke(LoginRequest $loginRequest): RedirectResponse
    {
        $credentials = [
            'email' => $loginRequest->validated('email'),
            'password' => $loginRequest->validated('password'),
        ];

        if (! Auth::attempt($credentials, $loginRequest->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return redirect()->intended(route('start.index'));
    }
}
