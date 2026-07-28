<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum EnvironmentEnum: string
{
    use HasNovaEnumLabelTrait;

    case LOCAL = 'local';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public function getLabel(): string
    {
        return match ($this) {
            EnvironmentEnum::LOCAL => __('Local'),
            EnvironmentEnum::STAGING => __('Staging'),
            EnvironmentEnum::PRODUCTION => __('Production'),
        };
    }
}
