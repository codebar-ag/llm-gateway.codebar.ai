<?php

use App\Enums\RoleEnum;
use App\Models\Activity;
use App\Models\User;
use App\Nova\Policies\ActivityPolicy;

it('allows administrators to view activity', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $activity = Activity::factory()->create();
    $policy = new ActivityPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $activity))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('denies mutating activity', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $activity = Activity::factory()->create();
    $policy = new ActivityPolicy;

    expect($policy->create($admin))->toBeFalse()
        ->and($policy->update($admin, $activity))->toBeFalse()
        ->and($policy->delete($admin, $activity))->toBeFalse()
        ->and($policy->restore($admin, $activity))->toBeFalse()
        ->and($policy->forceDelete($admin, $activity))->toBeFalse();
})->group('unit', 'nova', 'policies');
