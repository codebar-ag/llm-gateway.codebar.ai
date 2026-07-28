<?php

namespace App\Listeners\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Logout;

class SuccessfulLogoutListener
{
    public function handle(Logout $event): void
    {
        $user = $event->user;
        /** @var User $user */
        activity('logout')
            ->performedOn($user)
            ->causedBy($user)
            ->log('successful logout');
    }
}
