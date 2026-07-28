<?php

namespace App\Listeners\Auth;

use App\Enums\GuardEnum;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class SuccessfulLoginListener
{
    public function handle(Login $event): void
    {
        if ($event->guard !== GuardEnum::WEB->value) {
            return;
        }

        $user = $event->user;
        /** @var User $user */
        activity('login')
            ->performedOn($user)
            ->causedBy($user)
            ->log('successful login');
    }
}
