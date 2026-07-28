<?php

use App\Models\User;
use Illuminate\Support\Str;

it('find by uuid', function () {
    $uuid = Str::uuid()->toString();

    $model = User::factory()->create([
        'uuid' => $uuid,
    ]);

    expect($model->uuid)->toBe(User::findByUuid($uuid)->uuid);
})->group('unit', 'traits', 'uuid');

it('find by uuid or fail', function () {
    $uuid = Str::uuid()->toString();

    $model = User::factory()->create([
        'uuid' => $uuid,
    ]);

    expect($model->uuid)->toBe(User::findByUuidOrFail($uuid)->uuid);
})->group('unit', 'traits', 'uuid');

it('scope with uuid', function () {
    $uuid = Str::uuid()->toString();

    $model = User::factory()->create([
        'uuid' => $uuid,
    ]);

    User::factory(3)->create();

    expect($model->uuid)->toBe(User::withUuid($uuid)->first()->uuid);
})->group('unit', 'traits', 'uuid');

it('scope with uuids', function () {
    $uuid = Str::uuid()->toString();
    $uuid2 = Str::uuid()->toString();

    $model = User::factory()->create([
        'uuid' => $uuid,
    ]);

    $model2 = User::factory()->create([
        'uuid' => $uuid2,
    ]);

    User::factory(3)->create();

    expect($model->uuid)->toBeIn(User::withUuids([$uuid, $uuid2])->pluck('uuid')->toArray());
})->group('unit', 'traits', 'uuid');

it('get route key name', function () {
    $model = User::factory()->create();

    expect($model->getRouteKeyName())->toBe('uuid');
})->group('unit', 'traits', 'uuid');
