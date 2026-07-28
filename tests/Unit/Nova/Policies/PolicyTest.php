<?php

use App\Enums\RoleEnum;
use App\Models\Activity;
use App\Models\User;
use App\Nova\Policies\Policy;

it('allows only administrators to view', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);
    $activity = Activity::factory()->create();
    $policy = new class extends Policy {};

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $activity))->toBeTrue()
        ->and($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $activity))->toBeFalse();
})->group('unit', 'nova', 'policies');

it('denies every mutation even for administrators', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $activity = Activity::factory()->create();
    $policy = new class extends Policy {};

    expect($policy->create($admin))->toBeFalse()
        ->and($policy->update($admin, $activity))->toBeFalse()
        ->and($policy->delete($admin, $activity))->toBeFalse()
        ->and($policy->restore($admin, $activity))->toBeFalse()
        ->and($policy->forceDelete($admin, $activity))->toBeFalse();
})->group('unit', 'nova', 'policies');
