<?php

declare(strict_types=1);

use App\Enums\RoleEnum;
use App\Models\User;
use App\Nova\Policies\MicrosoftSSOIdentityPolicy;

it('denies create', function () {
    $user = User::factory()->createOne();
    $user->assignRole(RoleEnum::ADMINISTRATOR);
    $policy = new MicrosoftSSOIdentityPolicy;

    expect($policy->create($user))->toBeFalse();
})->group('unit', 'nova', 'policies');
