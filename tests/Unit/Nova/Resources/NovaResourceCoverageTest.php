<?php

declare(strict_types=1);

use App\Models\PasswordResetToken as PasswordResetTokenModel;
use App\Models\Role as RoleModel;
use App\Models\User as UserModel;
use App\Nova\Resources\PasswordResetToken as PasswordResetTokenNova;
use App\Nova\Resources\Role as RoleNova;
use App\Nova\Resources\User as UserNova;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\HttpFoundation\Request;

it('exposes nova hook methods on password reset token resource', function () {
    $request = NovaRequest::createFromBase(Request::create('/nova'));
    $resource = new PasswordResetTokenNova(new PasswordResetTokenModel);

    expect($resource->cards($request))->toBeArray();
    expect($resource->filters($request))->toBeArray();
    expect($resource->lenses($request))->toBeArray();
    expect($resource->actions($request))->toBeArray();
})->group('unit', 'nova');

it('exposes nova hook methods and unknown role label on role resource', function () {
    $request = NovaRequest::createFromBase(Request::create('/nova'));

    $role = RoleModel::factory()->create(['name' => 'orphan-role-not-in-enum']);

    $resource = new RoleNova($role);

    expect($resource->cards($request))->toBeArray();
    expect($resource->filters($request))->toBeArray();
    expect($resource->lenses($request))->toBeArray();
    expect($resource->actions($request))->toBeArray();

    $query = RoleModel::query();
    expect(RoleNova::relatableQuery($request, $query))->toBeInstanceOf(Builder::class);

    $nameField = collect($resource->fields($request))
        ->first(fn ($f) => $f instanceof Text && $f->attribute === 'name');

    expect($nameField)->toBeInstanceOf(Text::class);

    if (! $nameField instanceof Text) {
        throw new RuntimeException('Expected role name Text field.');
    }

    $nameField->resolveForDisplay($role);

    expect($nameField->displayedAs)->toBe('Unknown Role');
})->group('unit', 'nova');

it('exposes nova hook methods on user resource', function () {
    $request = NovaRequest::createFromBase(Request::create('/nova'));
    $user = UserModel::factory()->createOne();
    if (! $user instanceof UserModel) {
        throw new RuntimeException('Expected User model from factory.');
    }

    $resource = new UserNova($user);

    expect($resource->cards($request))->toBeArray();
    expect($resource->filters($request))->toBeArray();
    expect($resource->lenses($request))->toBeArray();
    expect($resource->actions($request))->toBeArray();
})->group('unit', 'nova');
