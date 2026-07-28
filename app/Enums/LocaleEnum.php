<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum LocaleEnum: string
{
    use HasNovaEnumLabelTrait;

    case DE = 'de_CH';
    case FR = 'fr_CH';
    case IT = 'it_CH';
    case EN = 'en_CH';

    public function getLabel(): string
    {
        return match ($this) {
            LocaleEnum::DE => __('German'),
            LocaleEnum::FR => __('French'),
            LocaleEnum::IT => __('Italian'),
            LocaleEnum::EN => __('English'),
        };
    }
}
