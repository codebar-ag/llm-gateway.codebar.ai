<?php

use App\Enums\RoleEnum;
use App\Models\User;

it('renders start.index page', function () {
    $user = User::factory()->create();

    $user->assignRole(RoleEnum::USER);

    $this->actingAs($user)
        ->get(route('start.index'))
        ->assertOk()
        ->assertSee(__('dashboard.title'), false);
})->group('controllers', 'dashboard');

it('renders start.index for administrator without user role', function () {
    $user = User::factory()->create();

    $user->assignRole(RoleEnum::ADMINISTRATOR);

    $this->actingAs($user)
        ->get(route('start.index'))
        ->assertOk()
        ->assertSee(__('dashboard.title'), false);
})->group('controllers', 'dashboard');

it('redirects unverified users to the email verification notice', function () {
    $user = User::factory()->unverified()->create();

    $user->assignRole(RoleEnum::USER);

    $this->actingAs($user)
        ->get(route('start.index'))
        ->assertRedirect(route('auth.verification.notice'));
})->group('controllers', 'dashboard');
