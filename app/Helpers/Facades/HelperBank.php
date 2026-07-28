<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperBank
 *
 * @method static string|null formatIban(string $iban)
 */
class HelperBank extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperBank::class;
    }
}
