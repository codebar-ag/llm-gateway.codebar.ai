<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BacklogStatus;
use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The dashboard's filter state, read once from the query string.
 *
 * A type of its own because every value is needed twice — to narrow the
 * listing, and to re-render the form that produced it — and because "is
 * anything filtered at all" is what decides whether the total is worth its
 * own COUNT.
 */
readonly class BacklogFilters
{
    private const LIKE_ESCAPE = '\\';

    public function __construct(
        public ?BacklogStatus $status,
        public string $model,
        public string $search,
        public string $gateway,
        public string $caller,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: BacklogStatus::tryFrom(self::text($request, 'status')),
            model: self::text($request, 'model'),
            search: self::text($request, 'q'),
            gateway: self::text($request, 'gateway'),
            caller: self::text($request, 'caller'),
        );
    }

    public function areEmpty(): bool
    {
        return $this->status === null
            && $this->model === ''
            && $this->search === ''
            && $this->gateway === ''
            && $this->caller === '';
    }

    /** @return Builder<BacklogEntry> */
    public function apply(): Builder
    {
        return BacklogEntry::query()
            ->when($this->status, fn (Builder $query, BacklogStatus $status) => $query->where('status', $status))
            ->when($this->model !== '', fn (Builder $query) => $query->where('model', $this->model))
            ->when($this->gateway !== '', fn (Builder $query) => $query->where('gateway_id', $this->gateway))
            // Through the same scope /v1/responses narrows by, so "this
            // caller's entries" has one definition: the dashboard and the API
            // cannot drift on what the keyless bucket contains.
            ->when($this->caller !== '', fn (Builder $query) => $query->ownedBy(
                $this->caller === ApiKeyLabel::ANONYMOUS ? null : $this->caller,
            ))
            ->when($this->search !== '', fn (Builder $query) => $query->whereRaw(
                'id like ? escape ?',
                [self::prefixPattern($this->search), self::LIKE_ESCAPE],
            ));
    }

    /**
     * The typed text as a LIKE prefix pattern, with its wildcards neutralised.
     *
     * `%` and `_` in a search box are characters an operator typed, not pattern
     * syntax — unescaped, a single `%` turns a prefix search into a scan for a
     * substring anywhere in the id. Escaping them needs the ESCAPE clause to be
     * explicit: Postgres defaults to backslash, SQLite has no default at all, so
     * relying on either would make one query mean two things.
     */
    private static function prefixPattern(string $search): string
    {
        $escaped = str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $search,
        );

        return $escaped.'%';
    }

    /** `?model[]=x` is a malformed URL rather than a filter, and reads as none. */
    private static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
