<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The window every number on the dashboard is measured over.
 *
 * One range for the whole page rather than a per-panel picker: the point of
 * reading tokens next to durations next to a caller breakdown is that they
 * describe the same slice of traffic, and three panels quietly on three
 * different windows is a comparison nobody can trust.
 */
enum UsageRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';
    case All = 'all';

    /**
     * A week by default: long enough that a quiet afternoon does not read as an
     * outage, short enough that last month's migration does not dominate it.
     */
    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Week;
    }

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Last 24 hours',
            self::Week => 'Last 7 days',
            self::Month => 'Last 30 days',
            self::All => 'All time',
        };
    }

    /** The short form the range picker shows. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Day => '24h',
            self::Week => '7d',
            self::Month => '30d',
            self::All => 'All',
        };
    }

    /** Null for `All`, which is the absence of a lower bound rather than a very old one. */
    public function since(): ?CarbonImmutable
    {
        return match ($this) {
            self::Day => CarbonImmutable::now()->subDay(),
            self::Week => CarbonImmutable::now()->subDays(7),
            self::Month => CarbonImmutable::now()->subDays(30),
            self::All => null,
        };
    }

    /**
     * How the volume chart groups its bars.
     *
     * A day of traffic drawn as one bar says nothing, and a year drawn per hour
     * is nine thousand bars — so the shortest range counts by hour and every
     * longer one by day.
     */
    public function bucketsByHour(): bool
    {
        return $this === self::Day;
    }
}
