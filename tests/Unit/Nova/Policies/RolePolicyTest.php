<?php

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Nova\Policies\RolePolicy;

it('allows administrators to view roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $role = Role::query()->firstOrFail();
    $policy = new RolePolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $role))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('allows administrators to view roles loaded from the user roles relationship', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);
    $policy = new RolePolicy;
    $roleFromRelation = $user->roles->firstOrFail();

    expect($policy->view($admin, $roleFromRelation))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('denies mutating roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $role = Role::query()->firstOrFail();
    $policy = new RolePolicy;

    expect($policy->create($admin))->toBeFalse()
        ->and($policy->update($admin, $role))->toBeFalse()
        ->and($policy->delete($admin, $role))->toBeFalse()
        ->and($policy->restore($admin, $role))->toBeFalse()
        ->and($policy->forceDelete($admin, $role))->toBeFalse();
})->group('unit', 'nova', 'policies');
