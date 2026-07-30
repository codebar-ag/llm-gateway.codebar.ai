<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The password an operator account is given.
 *
 * Always generated, never chosen. The dashboard has no self-service reset, so a
 * weak password here is one that stays weak until somebody notices — and the
 * two commands that write one are run by whoever already has a shell on the
 * server, who gains nothing from picking it themselves.
 *
 * `Str::random()` draws from `random_bytes()`, so this is CSPRNG output rather
 * than something seeded from the clock.
 */
final class GeneratedPassword
{
    /** 16 characters of base62 is ~95 bits — far past anything the login throttle would let through. */
    public const LENGTH = 16;

    public static function make(): string
    {
        return Str::random(self::LENGTH);
    }
}
