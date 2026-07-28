<?php

namespace App\Nova\Traits;

/**
 * Builds all resource labels from the consumer's RESOURCE_KEY constant.
 */
trait NovaLanguageTrait
{
    public static function createButtonLabel(): string
    {
        return self::translationString('nova.resources.'.static::RESOURCE_KEY.'.singular').' '.self::translationString('nova.resources.create');
    }

    public static function updateButtonLabel(): string
    {
        return self::translationString('nova.resources.'.static::RESOURCE_KEY.'.singular').' '.self::translationString('nova.resources.update');
    }

    public static function label(): string
    {
        return self::translationString('nova.resources.'.static::RESOURCE_KEY.'.plural');
    }

    public static function singularLabel(): string
    {
        return self::translationString('nova.resources.'.static::RESOURCE_KEY.'.singular');
    }

    private static function translationString(string $key): string
    {
        return trans($key);
    }
}
