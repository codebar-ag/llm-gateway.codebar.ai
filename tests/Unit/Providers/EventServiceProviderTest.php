<?php

use App\Listeners\Auth\ProvisionMicrosoftSsoUserListener;
use App\Listeners\Auth\SuccessfulLoginListener;
use App\Listeners\Auth\SuccessfulLogoutListener;
use App\Providers\EventServiceProvider;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserAuthenticated;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserRegistered;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;

it('is registered', function () {
    expect(app()->getProviders(EventServiceProvider::class))->not->toBeEmpty();
})->group('unit', 'providers');

it('listens for microsoft sso and auth events', function () {
    Event::fake();

    Event::assertListening(
        SSOUserRegistered::class,
        [ProvisionMicrosoftSsoUserListener::class, 'handleRegistered']
    );
    Event::assertListening(
        SSOUserAuthenticated::class,
        [ProvisionMicrosoftSsoUserListener::class, 'handleAuthenticated']
    );
    Event::assertListening(Login::class, SuccessfulLoginListener::class.'@handle');
    Event::assertListening(Logout::class, SuccessfulLogoutListener::class.'@handle');
    Event::assertListening(Registered::class, SendEmailVerificationNotification::class.'@handle');
})->group('unit', 'providers');
