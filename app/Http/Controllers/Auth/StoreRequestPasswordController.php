<?php

namespace App\Http\Controllers\Auth;

use App\Jobs\Auth\RequestPasswordJob;
use App\Requests\Auth\RequestPasswordRequest;
use Illuminate\Http\RedirectResponse;

final class StoreRequestPasswordController
{
    public function __invoke(RequestPasswordRequest $request): RedirectResponse
    {
        RequestPasswordJob::dispatch($request->validated('email'));

        flash(__('auth.passwords.sent'), 'success');

        return redirect()->route('auth.login');
    }
}
