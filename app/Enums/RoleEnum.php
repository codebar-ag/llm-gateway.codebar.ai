<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;
use Spatie\Permission\Middleware\RoleMiddleware;

enum RoleEnum: string
{
    use HasNovaEnumLabelTrait;

    case ADMINISTRATOR = 'administrator';
    case USER = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            RoleEnum::ADMINISTRATOR => __('enums.role.administrator'),
            RoleEnum::USER => __('enums.role.user'),
        };
    }

    public function route(): string
    {
        return 'role:'.$this->value;
    }

    /**
     * Spatie {@see RoleMiddleware}: allow either role on the main web dashboard.
     */
    public static function webDashboardMiddleware(): string
    {
        return 'role:'.self::ADMINISTRATOR->value.'|'.self::USER->value;
    }
}
