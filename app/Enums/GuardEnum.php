<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum GuardEnum: string
{
    use HasNovaEnumLabelTrait;

    case WEB = 'web';

    public function getLabel(): string
    {
        return match ($this) {
            GuardEnum::WEB => __('Web'),
        };
    }
}
