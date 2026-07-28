<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum ProviderEnum: string
{
    use HasNovaEnumLabelTrait;

    case MICROSOFT_OFFICE_365 = 'microsoft';

    public function getLabel(): string
    {
        return match ($this) {
            ProviderEnum::MICROSOFT_OFFICE_365 => __('Microsoft Office 365'),
        };
    }
}
