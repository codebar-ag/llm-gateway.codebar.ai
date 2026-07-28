<?php

namespace App\Listeners\Auth;

use App\Enums\GuardEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use CodebarAg\MicrosoftEntraSSO\Contracts\SSOAuthenticatable;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserAuthenticated;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserRegistered;

final class ProvisionMicrosoftSsoUserListener
{
    public function handleRegistered(SSOUserRegistered $event): void
    {
        $this->provision($event->user);
    }

    public function handleAuthenticated(SSOUserAuthenticated $event): void
    {
        $this->provision($event->user);
    }

    private function provision(SSOAuthenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        Role::findOrCreate(RoleEnum::USER->value, GuardEnum::WEB->value);

        if (! $user->hasRole(RoleEnum::USER)) {
            $user->assignRole(RoleEnum::USER);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }
}
