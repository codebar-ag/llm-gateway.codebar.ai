<?php

namespace App\Helpers\Facades;

use Carbon\Carbon;
use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperDate
 *
 * @method static string formatDateTime(Carbon $date, string|null $locale = null)
 * @method static string formatDate(Carbon $date)
 */
class HelperDate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperDate::class;
    }
}
