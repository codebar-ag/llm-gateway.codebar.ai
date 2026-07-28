<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperPhone
 *
 * @method static string format(string $country, string $number)
 */
class HelperPhone extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperPhone::class;
    }
}
