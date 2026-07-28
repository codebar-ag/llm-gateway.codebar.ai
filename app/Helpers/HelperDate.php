<?php

namespace App\Helpers;

use Carbon\Carbon;

class HelperDate
{
    public function formatDateTime(Carbon $date, ?string $locale = null): string
    {
        return match ($locale ?? app()->getLocale()) {
            'de', 'de_CH' => $date->format('d.m.Y H:i').' Uhr',
            'en', 'en_CH' => $date->format('d.m.Y g:i a'),
            default => $date->format('d.m.Y H:i')
        };
    }

    public function formatDate(Carbon $date): string
    {
        return $date->format('d.m.Y');
    }
}
