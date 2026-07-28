<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class HelperPhone
{
    public function format(string $country, string $number): string
    {
        return match (Str::lower($country)) {
            // CHE always +41791234567
            'che' => substr($number, 0, 3).' (0) '.substr($number, 3, 2).' '.substr($number, 5, 3).' '.substr($number, 8, 2).' '.substr($number, 10, 2),
            default => chunk_split($number, 3, ' '),
        };
    }
}
