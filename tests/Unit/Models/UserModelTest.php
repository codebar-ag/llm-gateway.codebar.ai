<?php

use App\Enums\LocaleEnum;
use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;

it('can create a user model', function () {
    $model = User::factory()->create();
    expect($model)->toBeInstanceOf(User::class);
})->group('unit', 'models');

it('can soft delete a user', function () {
    $model = User::factory()->create();
    $model->delete();
    $this->assertSoftDeleted($model);
})->group('unit', 'models');

it('can delete a user', function () {
    $model = User::factory()->create();
    $this->assertTrue($model->forceDelete());
})->group('unit', 'models');

it('can get a user gravatar url', function () {
    $model = User::factory()->create();
    expect($model->getGravatarUrl())->toBe('https://www.gravatar.com/avatar/'.md5($model->email).'?s=200&d=mp');
    expect($model->getGravatarUrl(400))->toBe('https://www.gravatar.com/avatar/'.md5($model->email).'?s=400&d=mp');
})->group('unit', 'models');

it('returns preferred locale from enum cast', function () {
    $model = User::factory()->create(['locale' => LocaleEnum::EN]);

    expect($model->preferredLocale())->toBe(LocaleEnum::EN->value);
})->group('unit', 'models');

it('hydrates roles as the application Role model for Spatie permission config', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);

    expect(get_class($user->roles()->getRelated()))->toBe(Role::class)
        ->and($user->roles->first())->toBeInstanceOf(Role::class);
})->group('unit', 'models');

it('uses account email as contact email for notifications', function () {
    $model = User::factory()->create(['email' => 'notify@example.com']);

    expect($model->contact_email)->toBe('notify@example.com');
})->group('unit', 'models');
