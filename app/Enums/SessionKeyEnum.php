<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum SessionKeyEnum: string
{
    use HasNovaEnumLabelTrait;

    case LANGUAGE = 'language';

    public function getLabel(): string
    {
        return match ($this) {
            SessionKeyEnum::LANGUAGE => __('Language'),
        };
    }
}
