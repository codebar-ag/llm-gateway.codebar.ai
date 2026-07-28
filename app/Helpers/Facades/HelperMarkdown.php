<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \App\Helpers\HelperMarkdown
 *
 * @method static string formatMarkdown(string $markdown)
 */
class HelperMarkdown extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Helpers\HelperMarkdown::class;
    }
}
