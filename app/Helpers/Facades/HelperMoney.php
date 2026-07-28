<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperMoney
 *
 * @method static string format(int|float $number, string $currency = 'CHF')
 * @method static string formatLeft(int|float $number, string $currency = 'CHF')
 * @method static float roundMoney(int|float $money)
 * @method static float roundMoneyUp(int|float $money)
 * @method static float roundMoneyDown(int|float $money)
 */
class HelperMoney extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperMoney::class;
    }
}
