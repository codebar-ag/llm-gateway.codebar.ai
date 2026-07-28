<?php

use App\Enums\RoleEnum;
use App\Models\User as UserModel;

it('index', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaIndex('users')
        ->assertOk();

    $this->get('/nova/resources/users')
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('users')
        ->assertForbidden();
})->group('nova');

it('detail', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaDetail('users', $userUser->id)
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('users')
        ->assertForbidden();
})->group('nova');

it('create', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaCreate('users')
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('users')
        ->assertForbidden();
})->group('nova');

it('edit', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaEdit('users', $userUser->id)
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('users')
        ->assertForbidden();
})->group('nova');
