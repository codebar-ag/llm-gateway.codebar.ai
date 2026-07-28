<?php

use App\Enums\RoleEnum;
use App\Models\Activity;
use App\Models\User as UserModel;
use App\Nova\Resources\Activity as ActivityResource;
use Laravel\Nova\Http\Requests\NovaRequest;

it('index', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaIndex('activities')
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('activities')
        ->assertForbidden();
})->group('nova');

it('labels the created_at field independently from properties', function () {
    $resource = new ActivityResource(Activity::first());

    $fields = collect($resource->fields(app(NovaRequest::class)));

    $createdAt = $fields->first(fn ($field) => ($field->attribute ?? null) === 'created_at');
    $properties = $fields->first(fn ($field) => ($field->attribute ?? null) === 'properties');

    expect($createdAt->name)->toBe(__('nova.resources.activity.fields.created_at'))
        ->and($createdAt->name)->not->toBe($properties->name);
})->group('nova');

it('detail', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaDetail('activities', Activity::first()->id)
        ->assertOk();

    $this->be($userUser);

    $this->novaIndex('activities')
        ->assertForbidden();
})->group('nova');

it('create', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaCreate('activities')
        ->assertForbidden();

    $this->be($userUser);

    $this->novaIndex('activities')
        ->assertForbidden();
})->group('nova');

it('edit', function () {
    $userAdmin = UserModel::factory()->create();
    $userUser = UserModel::factory()->create();

    $userAdmin->assignRole(RoleEnum::ADMINISTRATOR);
    $userUser->assignRole(RoleEnum::USER);

    $this->be($userAdmin);

    $this->novaEdit('activities', Activity::first()->id)
        ->assertForbidden();

    $this->be($userUser);

    $this->novaIndex('activities')
        ->assertForbidden();
})->group('nova');
