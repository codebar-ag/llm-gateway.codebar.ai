<?php

namespace App\Http\Controllers\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class StoreResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        $passwordResetToken = PasswordResetToken::query()
            ->where('email', $email)
            ->where('created_at', '>', now()->subMinutes((int) config('auth.passwords.users.expire', 60)))
            ->first();

        if ($passwordResetToken === null || ! Hash::check($request->validated('token'), $passwordResetToken->token)) {
            throw ValidationException::withMessages([
                'email' => __('auth.passwords.token'),
            ]);
        }

        $user = User::query()
            ->where('email', $email)
            ->sole();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        Auth::attempt([
            'email' => $email,
            'password' => $request->validated('password'),
        ], true);

        PasswordResetToken::query()
            ->where('email', $email)
            ->delete();

        session()->regenerate();

        flash(__('auth.passwords.reset'), 'success');

        return redirect()->intended();
    }
}
