<?php

namespace App\Models;

use App\Traits\HasActivityTrait;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as BaseRole;

class Role extends BaseRole
{
    use HasActivityTrait;

    /** @use HasFactory<RoleFactory> */
    use HasFactory;
}
