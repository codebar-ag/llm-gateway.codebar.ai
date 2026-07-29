<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A queued entry's place in line, and what that place is expected to cost.
 *
 * `estimatedSeconds` counts through to the entry's own answer rather than to
 * the moment a worker picks it up: "how long until I can collect this" is the
 * question a caller polling for a result is actually asking.
 *
 * The array shape lives here rather than in the resource because two entry
 * points render it — `/v1/responses` through `BacklogEntryResource` and
 * `/v1/chat/completions` from a hand-built body — and they have to agree.
 */
final readonly class QueuePlace
{
    public function __construct(
        public int $position,
        public int $depth,
        public int $workers,
        public int $estimatedSeconds,
        public int $estimatedCompletionAt,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'depth' => $this->depth,
            'workers' => $this->workers,
            'estimated_seconds' => $this->estimatedSeconds,
            'estimated_completion_at' => $this->estimatedCompletionAt,
        ];
    }
}
