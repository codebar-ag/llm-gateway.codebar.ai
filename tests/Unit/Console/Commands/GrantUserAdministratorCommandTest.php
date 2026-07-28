<?php

use App\Enums\GuardEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('grants administrator role without removing existing roles', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);

    $exitCode = Artisan::call('user:grant-administrator', ['email' => $user->email]);

    expect($exitCode)->toBe(0);

    $user = $user->fresh();
    expect($user)->not->toBeNull()
        ->and($user->hasRole(RoleEnum::ADMINISTRATOR))->toBeTrue()
        ->and($user->hasRole(RoleEnum::USER))->toBeTrue();
})->group('unit', 'console');

it('fails when no user exists for the email', function () {
    $exitCode = Artisan::call('user:grant-administrator', ['email' => 'nobody@example.test']);

    expect($exitCode)->not->toBe(0);
})->group('unit', 'console');

it('fails when email is invalid', function () {
    $exitCode = Artisan::call('user:grant-administrator', ['email' => 'not-a-valid-email']);

    expect($exitCode)->not->toBe(0);
})->group('unit', 'console');

it('fails when administrator role is missing', function () {
    Role::query()
        ->where('name', RoleEnum::ADMINISTRATOR->value)
        ->where('guard_name', GuardEnum::WEB->value)
        ->delete();

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);

    $exitCode = Artisan::call('user:grant-administrator', ['email' => $user->email]);

    expect($exitCode)->not->toBe(0)
        ->and($user->fresh()->hasRole(RoleEnum::ADMINISTRATOR))->toBeFalse();
})->group('unit', 'console');
