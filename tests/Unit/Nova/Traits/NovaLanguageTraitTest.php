<?php

use App\Nova\Resources\Activity;
use App\Nova\Resources\MicrosoftSSOIdentity;
use App\Nova\Resources\PasswordResetToken;
use App\Nova\Resources\Role;
use App\Nova\Resources\User;

it('builds all labels from the resource key', function (string $resource, string $key) {
    expect($resource::label())->toBe(__('nova.resources.'.$key.'.plural'))
        ->and($resource::singularLabel())->toBe(__('nova.resources.'.$key.'.singular'))
        ->and($resource::createButtonLabel())->toBe(__('nova.resources.'.$key.'.singular').' '.__('nova.resources.create'))
        ->and($resource::updateButtonLabel())->toBe(__('nova.resources.'.$key.'.singular').' '.__('nova.resources.update'));
})->with([
    'user' => [User::class, 'user'],
    'role' => [Role::class, 'role'],
    'activity' => [Activity::class, 'activity'],
    'microsoft sso identity' => [MicrosoftSSOIdentity::class, 'microsoft_sso_identity'],
    'password reset token' => [PasswordResetToken::class, 'password_reset_token'],
])->group('unit', 'nova', 'traits');
