<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which endpoint an entry was logged for.
 *
 * Every call this app forwards is recorded, but not every call goes through
 * the queue: an embedding or a moderation is answered while the caller waits,
 * so its entry is written once, already over. The kind is what keeps the two
 * apart — the Responses API only knows the ones it queued, and the dashboard
 * shows them all.
 */
enum BacklogKind: string
{
    case Chat = 'chat';
    case Embeddings = 'embeddings';
    case Moderations = 'moderations';

    public function label(): string
    {
        return match ($this) {
            self::Chat => 'chat',
            self::Embeddings => 'embeddings',
            self::Moderations => 'moderations',
        };
    }

    /**
     * Whether an entry of this kind is answered by a worker.
     *
     * Only these have a place in a queue, a retry, or an id worth polling —
     * which is why they are the only ones the Responses API lists, shows,
     * cancels or deletes.
     */
    public function isQueued(): bool
    {
        return $this === self::Chat;
    }
}
