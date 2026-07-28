<?php

namespace App\Providers;

use App\Listeners\Auth\ProvisionMicrosoftSsoUserListener;
use App\Listeners\Auth\SuccessfulLoginListener;
use App\Listeners\Auth\SuccessfulLogoutListener;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserAuthenticated;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserRegistered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            SSOUserRegistered::class,
            [ProvisionMicrosoftSsoUserListener::class, 'handleRegistered']
        );

        Event::listen(
            SSOUserAuthenticated::class,
            [ProvisionMicrosoftSsoUserListener::class, 'handleAuthenticated']
        );

        Event::listen(
            events: Login::class,
            listener: SuccessfulLoginListener::class.'@handle'
        );

        Event::listen(
            events: Logout::class,
            listener: SuccessfulLogoutListener::class.'@handle'
        );

        Event::listen(
            events: Registered::class,
            listener: SendEmailVerificationNotification::class.'@handle'
        );
    }
}
