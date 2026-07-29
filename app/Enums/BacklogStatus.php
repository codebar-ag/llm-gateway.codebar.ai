<?php

declare(strict_types=1);

namespace App\Enums;

enum BacklogStatus: string
{
    case Queued = 'queued';
    case Running = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isCancellable(): bool
    {
        return $this === self::Queued;
    }

    /**
     * The statuses a caller may still cancel from.
     *
     * `BacklogEntry::cancelIfQueued()` narrows its UPDATE with this rather
     * than naming a case of its own, so the rule is stated once and a status
     * added later cannot disagree with `isCancellable()`.
     *
     * @return array<int, self>
     */
    public static function cancellable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->isCancellable()));
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'queued',
            self::Running => 'in progress',
            self::Completed => 'completed',
            self::Failed => 'failed',
            self::Cancelled => 'cancelled',
        };
    }

    /** One of the design system's semantic tones, for Badge/StatusBadge. */
    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
