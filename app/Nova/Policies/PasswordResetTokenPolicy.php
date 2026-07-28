<?php

namespace App\Nova\Policies;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class PasswordResetTokenPolicy extends Policy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): Response|bool
    {
        return $user->hasRole(RoleEnum::ADMINISTRATOR);
    }
}
