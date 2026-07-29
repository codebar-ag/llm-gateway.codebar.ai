<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A span of seconds, said the way an operator reads it.
 *
 * Two renderings of the same number: `human()` for the cell and `exact()` for
 * the tooltip beside it, so a rounded "25m" is never the only thing on the
 * page — the measurement it was rounded from is still there to be read.
 */
final class Duration
{
    private const MINUTE = 60;

    private const HOUR = 3600;

    private const DAY = 86400;

    /** What an unmeasurable span reads as, matching the dashboard's other blanks. */
    private const NONE = '—';

    /**
     * The span at the coarsest two units that still describe it.
     *
     * The smaller unit is dropped when it is zero, so a round two minutes reads
     * "2m" rather than "2m 0s".
     */
    public static function human(?int $seconds): string
    {
        if ($seconds === null) {
            return self::NONE;
        }

        $total = self::clamped($seconds);

        if ($total < self::MINUTE) {
            return $total.'s';
        }

        if ($total < self::HOUR) {
            return self::pair(intdiv($total, self::MINUTE), 'm', $total % self::MINUTE, 's');
        }

        if ($total < self::DAY) {
            return self::pair(intdiv($total, self::HOUR), 'h', intdiv($total % self::HOUR, self::MINUTE), 'm');
        }

        return self::pair(intdiv($total, self::DAY), 'd', intdiv($total % self::DAY, self::HOUR), 'h');
    }

    /** The unrounded measurement, for the tooltip next to `human()`. */
    public static function exact(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return self::clamped($seconds).'s';
    }

    /**
     * A span is never negative.
     *
     * The two timestamps behind one of these can be stamped by different
     * machines, and a clock skew of a second or two between them has to read
     * as "0s" rather than as the impossible "-3s".
     */
    private static function clamped(int $seconds): int
    {
        return max(0, $seconds);
    }

    private static function pair(int $large, string $largeUnit, int $small, string $smallUnit): string
    {
        if ($small === 0) {
            return $large.$largeUnit;
        }

        return $large.$largeUnit.' '.$small.$smallUnit;
    }
}
