<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Arr;

/**
 * Which endpoint queued an entry.
 *
 * Every call that costs the upstream real compute goes through the backlog —
 * chat completions, embeddings and moderations alike. This app exists to be
 * the one place that load is serialised, and an endpoint that answers inline
 * is an endpoint that lets a caller hammer the backend directly.
 *
 * The kind is what a worker reads to know which call to make, and what a poll
 * reads to know which shape to answer with: an embedding comes back as a
 * vector list, not as a chat message.
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

    public function isChat(): bool
    {
        return $this === self::Chat;
    }

    /**
     * The route a caller collects this kind of answer from.
     *
     * Named without the `gateway.` prefix; a caller who addressed a gateway
     * gets that prefix added — see `PollUrl`.
     */
    public function pollRoute(): string
    {
        return match ($this) {
            self::Chat => 'responses.show',
            self::Embeddings => 'embeddings.show',
            self::Moderations => 'moderations.show',
        };
    }

    /** What the `object` field of the 202 says the caller is now holding. */
    public function queuedObject(): string
    {
        return match ($this) {
            self::Chat => 'response.queued',
            self::Embeddings => 'embedding.queued',
            self::Moderations => 'moderation.queued',
        };
    }

    /**
     * The stored answer, in one line, for the dashboard.
     *
     * A chat entry has its own text and needs no summary. The other two are
     * stored as the upstream's whole body so the caller can collect it, and a
     * batch of 4000-dimension vectors rendered into a page is thousands of
     * screens of numbers — the shape is what an operator is actually looking
     * for, and the body itself is still one click away in the response pane.
     *
     * @param  array<array-key, mixed>  $body
     */
    public function summarise(array $body): ?string
    {
        return match ($this) {
            self::Chat => null,
            self::Embeddings => self::embeddingsSummary($body),
            self::Moderations => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        };
    }

    /** @param  array<array-key, mixed>  $body */
    private static function embeddingsSummary(array $body): string
    {
        $vectors = Arr::get($body, 'data');
        $vectors = is_array($vectors) ? $vectors : [];

        $first = Arr::get($body, 'data.0.embedding');
        $dimensions = is_array($first) ? count($first) : 0;

        return count($vectors).' '.str('embedding')->plural(count($vectors))
            .' × '.$dimensions.' '.str('dimension')->plural($dimensions);
    }
}
