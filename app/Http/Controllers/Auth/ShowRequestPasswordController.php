<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;

final class ShowRequestPasswordController
{
    public function __invoke(): View
    {
        return view('auth.forgot-password');
    }
}
