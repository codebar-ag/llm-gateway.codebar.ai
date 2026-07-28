<?php

declare(strict_types=1);

use App\Enums\RoleEnum;
use App\Listeners\Auth\ProvisionMicrosoftSsoUserListener;
use App\Models\User;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserAuthenticated;
use CodebarAg\MicrosoftEntraSSO\Events\SSOUserRegistered;

it('assigns the user role and verifies email on SSOUserRegistered', function () {
    $user = User::factory()->unverified()->create();
    expect($user->hasRole(RoleEnum::USER))->toBeFalse();

    $listener = new ProvisionMicrosoftSsoUserListener;
    $listener->handleRegistered(new SSOUserRegistered($user, 'web', []));

    $user->refresh();
    expect($user->hasRole(RoleEnum::USER))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
})->group('unit', 'listeners');

it('assigns the user role and verifies email on SSOUserAuthenticated', function () {
    $user = User::factory()->unverified()->create();
    expect($user->hasRole(RoleEnum::USER))->toBeFalse();

    $listener = new ProvisionMicrosoftSsoUserListener;
    $listener->handleAuthenticated(new SSOUserAuthenticated($user, 'web', []));

    $user->refresh();
    expect($user->hasRole(RoleEnum::USER))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
})->group('unit', 'listeners');

it('keeps existing administrator role when assigning user', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole(RoleEnum::ADMINISTRATOR);
    expect($user->hasRole(RoleEnum::USER))->toBeFalse();

    $listener = new ProvisionMicrosoftSsoUserListener;
    $listener->handleRegistered(new SSOUserRegistered($user, 'web', []));

    $user->refresh();
    expect($user->hasRole(RoleEnum::ADMINISTRATOR))->toBeTrue()
        ->and($user->hasRole(RoleEnum::USER))->toBeTrue();
})->group('unit', 'listeners');

it('does not duplicate user role or change verification when already provisioned', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);
    $verifiedAt = $user->email_verified_at;

    $listener = new ProvisionMicrosoftSsoUserListener;
    $listener->handleAuthenticated(new SSOUserAuthenticated($user, 'web', []));

    $user->refresh();
    expect($user->getRoleNames()->count())->toBe(1)
        ->and($user->hasRole(RoleEnum::USER))->toBeTrue()
        ->and($user->email_verified_at?->equalTo($verifiedAt))->toBeTrue();
})->group('unit', 'listeners');
