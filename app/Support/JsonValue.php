<?php

declare(strict_types=1);

namespace App\Support;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Typed boundary between arbitrary decoded JSON and `Arr::get`.
 *
 * Every driver reads a body it did not shape, so every driver needs the same
 * three narrowings. The runtime check in `at()` looks redundant — `Arr::get`
 * returns the default for a subject it cannot read — but it is what narrows
 * `mixed` to something `Arr::get` accepts. Inlining it costs three level-10
 * errors per call site.
 */
class JsonValue
{
    public static function at(mixed $subject, string $key): mixed
    {
        if (! is_array($subject) && ! $subject instanceof ArrayAccess) {
            return null;
        }

        return Arr::get($subject, $key);
    }

    public static function stringAt(mixed $subject, string $key): string
    {
        return self::asString(self::at($subject, $key));
    }

    public static function asString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Null rather than zero when the key is missing, so "unknown" stays distinguishable. */
    public static function intAt(mixed $subject, string $key): ?int
    {
        $value = self::at($subject, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<array-key, mixed> */
    public static function arrayAt(mixed $subject, string $key): array
    {
        $value = self::at($subject, $key);

        return is_array($value) ? $value : [];
    }
}
