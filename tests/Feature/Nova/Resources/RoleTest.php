<?php

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User as UserModel;

it('index', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaIndex('roles')
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('roles')
        ->assertForbidden();
})->group('nova');

it('detail', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaDetail('roles', Role::first()->id)
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('roles')
        ->assertForbidden();
})->group('nova');

it('create', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaCreate('roles')
        ->assertForbidden();

    $this->be($userUser);

    $this->novaIndex('roles')
        ->assertForbidden();
})->group('nova');

it('edit', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaEdit('roles', Role::first()->id)
        ->assertForbidden();

    $this->be($userUser);

    $this->novaIndex('roles')
        ->assertForbidden();
})->group('nova');
