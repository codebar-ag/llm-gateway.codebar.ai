<?php

use App\Enums\RoleEnum;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Nova\Policies\PasswordResetTokenPolicy;

it('allows administrators to view tokens', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);

    $token = PasswordResetToken::query()->create([
        'email' => 'tok@example.com',
        'token' => 'reset-token',
    ]);

    $policy = new PasswordResetTokenPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $token))->toBeTrue()
        ->and($policy->delete($admin, $token))->toBeTrue();
})->group('unit', 'nova', 'policies');

it('denies create update restore and force delete', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::ADMINISTRATOR);
    $token = PasswordResetToken::query()->create([
        'email' => 'tok2@example.com',
        'token' => 'reset-token-2',
    ]);

    $policy = new PasswordResetTokenPolicy;

    expect($policy->create($admin))->toBeFalse()
        ->and($policy->update($admin, $token))->toBeFalse()
        ->and($policy->restore($admin, $token))->toBeFalse()
        ->and($policy->forceDelete($admin, $token))->toBeFalse();
})->group('unit', 'nova', 'policies');
