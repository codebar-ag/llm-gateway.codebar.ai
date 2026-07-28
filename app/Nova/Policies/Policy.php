<?php

namespace App\Nova\Policies;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Nova\Resources\Resource;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract base for Nova resource policies — not a dedicated policy
 * itself (mirrors {@see \App\Nova\Resources\Resource}).
 *
 * Safe default: administrators may view the resource; nobody may mutate it.
 * Concrete policies override individual abilities where needed.
 */
abstract class Policy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response|bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $model): Response|bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): Response|bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $model): Response|bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $model): Response|bool
    {
        return false;
    }
}
