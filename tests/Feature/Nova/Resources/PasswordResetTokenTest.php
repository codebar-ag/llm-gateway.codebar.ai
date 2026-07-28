<?php

use App\Enums\RoleEnum;
use App\Models\PasswordResetToken;
use App\Models\User as UserModel;

it('index', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    PasswordResetToken::query()->create([
        'email' => $userAdmin->email,
        'token' => hash('sha256', 'plain-token'),
    ]);

    $this->be($userAdmin);

    $this->novaIndex('password-reset-tokens')
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('password-reset-tokens')
        ->assertForbidden();
})->group('nova');

it('detail', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    PasswordResetToken::query()->create([
        'email' => $userAdmin->email,
        'token' => hash('sha256', 'plain-token'),
    ]);

    $this->be($userAdmin);

    $this->novaDetail('password-reset-tokens', rawurlencode($userAdmin->email))
        ->assertOk();

    $this->be($userUser);

    $this->novaDetail('password-reset-tokens', rawurlencode($userAdmin->email))
        ->assertForbidden();
})->group('nova');

it('create', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaCreate('password-reset-tokens')
        ->assertForbidden();

    $this->be($userUser);

    $this->novaCreate('password-reset-tokens')
        ->assertForbidden();
})->group('nova');

it('edit', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    PasswordResetToken::query()->create([
        'email' => $userAdmin->email,
        'token' => hash('sha256', 'plain-token'),
    ]);

    $this->be($userAdmin);

    $this->novaEdit('password-reset-tokens', rawurlencode($userAdmin->email))
        ->assertForbidden();

    $this->be($userUser);

    $this->novaEdit('password-reset-tokens', rawurlencode($userAdmin->email))
        ->assertForbidden();
})->group('nova');
