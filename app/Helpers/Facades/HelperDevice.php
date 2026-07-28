<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperDevice
 *
 * @method static bool isMobileDevice()
 */
class HelperDevice extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperDevice::class;
    }
}
