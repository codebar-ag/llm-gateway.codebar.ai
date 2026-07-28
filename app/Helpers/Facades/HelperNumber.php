<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperNumber
 *
 * @method static string format(int|float|null $number)
 */
class HelperNumber extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperNumber::class;
    }
}
