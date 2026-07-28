<?php

namespace App\Nova\Policies;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Models\Role;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function view(User $user, User $model): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function create(User $user): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function update(User $user, User $model): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function delete(User $user, User $model): Response|bool
    {
        return $user->id !== $model->id && $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function restore(User $user, User $model): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    public function forceDelete(User $user, User $model): Response|bool
    {
        return false;
    }

    public function attachRole(User $user, User $model, Role $role): Response|bool
    {
        return ! $model->hasRole($role);
    }
}
