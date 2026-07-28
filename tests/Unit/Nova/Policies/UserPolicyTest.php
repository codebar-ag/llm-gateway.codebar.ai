<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Nova\Policies\UserPolicy;
use Spatie\Permission\Models\Role;

it('allows administrators for standard abilities', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);

    $other = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $other))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $other))->toBeTrue()
        ->and($policy->restore($admin, $other))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('prevents deleting self', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);

    $policy = new UserPolicy;

    expect($policy->delete($admin, $admin))->toBeFalse();
})->group('unit', 'nova', 'policies');

it('denies force delete', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $other = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->forceDelete($admin, $other))->toBeFalse();
})->group('unit', 'nova', 'policies');

it('allows attaching role when user does not have it', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $target = User::factory()->create();
    $role = Role::query()->where('name', RoleEnum::USER->value)->firstOrFail();

    $policy = new UserPolicy;

    expect($policy->attachRole($admin, $target, $role))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('denies attaching role user already has', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $target = User::factory()->create();
    $target->assignRole(RoleEnum::USER);
    $role = Role::query()->where('name', RoleEnum::USER->value)->firstOrFail();

    $policy = new UserPolicy;

    expect($policy->attachRole($admin, $target, $role))->toBeFalse();
})->group('unit', 'nova', 'policies');
