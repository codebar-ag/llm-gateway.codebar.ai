<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperFile
 *
 * @method static string formatBytes(int $size, int $precision = 1)
 * @method static string nameWithDate(string $name)
 * @method static string nameWithDateTime(string $name)
 */
class HelperFile extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperFile::class;
    }
}
