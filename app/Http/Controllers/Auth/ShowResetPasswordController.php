<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class ShowResetPasswordController
{
    public function __invoke(Request $request, string $token): View
    {
        return view('auth.reset-password')
            ->with([
                'token' => $token,
                'email' => Arr::get($request->query(), 'email'),
            ]);
    }
}
