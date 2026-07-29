<?php

declare(strict_types=1);

namespace App\Enums;

enum TunnelState: string
{
    /** cloudflared reports live connections to the edge. */
    case Ready = 'ready';

    /** Configured, but not answering or not connected. */
    case Down = 'down';

    /** No metrics endpoint configured — nothing to report. */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'tunnel up',
            self::Down => 'tunnel down',
            self::Unknown => 'tunnel unknown',
        };
    }

    /** One of the design system's semantic tones, for Badge/StatusBadge. */
    public function tone(): string
    {
        return match ($this) {
            self::Ready => 'success',
            self::Down => 'danger',
            self::Unknown => 'neutral',
        };
    }
}
