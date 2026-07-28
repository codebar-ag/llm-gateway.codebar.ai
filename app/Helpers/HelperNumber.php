<?php

namespace App\Helpers;

class HelperNumber
{
    public function format(int|float|null $number, int $decimals = 2, string $decimalSeparator = '.', string $thousandSeparator = "'"): string
    {
        return $number === null
            ? self::defaultValue($decimals)
            : number_format($number, $decimals, $decimalSeparator, $thousandSeparator);
    }

    protected static function defaultValue(int $decimals = 0): string
    {
        return number_format(0, $decimals);
    }
}
