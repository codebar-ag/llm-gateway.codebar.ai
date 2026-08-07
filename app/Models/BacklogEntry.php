<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BacklogKind;
use App\Enums\BacklogStatus;
use App\Support\JsonValue;
use App\Support\ToolCalls;
use App\Support\UpstreamFailure;
use Carbon\CarbonImmutable;
use Database\Factories\BacklogEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * @property string $id
 * @property BacklogStatus $status
 * @property BacklogKind $kind
 * @property string|null $model
 * @property string|null $gateway_id
 * @property Gateway|null $gateway
 * @property array<string, mixed>|null $request
 * @property array<string, string>|null $headers
 * @property array<array-key, mixed>|null $output
 * @property array<string, int|null>|null $usage
 * @property array<string, mixed>|null $error
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $api_key_hash
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $pruned_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<BacklogEntry> query()
 */
class BacklogEntry extends Model
{
    /** @use HasFactory<BacklogEntryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'backlog_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'id', 'status', 'kind', 'model', 'gateway_id', 'request', 'headers', 'output', 'usage',
        'error', 'attempts', 'max_attempts', 'api_key_hash',
        'started_at', 'finished_at',
    ];

    /** The caller's headers carry their key and never leave this app. @var list<string> */
    protected $hidden = ['headers'];

    /**
     * The upstream this entry was accepted for.
     *
     * Trashed gateways are included on purpose: retiring a gateway must not
     * detach the history of what it answered.
     *
     * @return BelongsTo<Gateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class)->withTrashed();
    }

    public static function newId(): string
    {
        return 'resp_'.Str::uuid7()->getHex();
    }

    /**
     * How many entries sit in each status, keyed by status value.
     *
     * Narrowed to int here rather than at each caller: Postgres reports
     * `count(*)` as a string and SQLite as an integer, and neither the
     * dashboard nor its total should have to know which driver it is on.
     *
     * @return Collection<array-key, int>
     */
    public static function statusCounts(): Collection
    {
        return static::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => is_numeric($total) ? (int) $total : 0);
    }

    /**
     * What each caller has queued, one row per key hash.
     *
     * `toBase()` is deliberate. It keeps the soft-delete scope — global scopes
     * are applied before the query is handed down — while returning plain rows
     * instead of hydrating four-attribute models: under `Model::shouldBeStrict`
     * anything later touching an attribute this SELECT did not name would raise
     * a missing-attribute error rather than read a null.
     *
     * Types are narrowed here rather than at each caller, as `statusCounts()`
     * does: `count(*)` is a string on Postgres and an integer on SQLite, and
     * the aliased `min`/`max` bypass the model's date casts entirely.
     *
     * @return Collection<int, array{api_key_hash: string|null, entries: int, first_seen: CarbonImmutable, last_seen: CarbonImmutable}>
     */
    public static function callerActivity(): Collection
    {
        return static::query()
            ->selectRaw('api_key_hash, count(*) as entries, min(created_at) as first_seen, max(created_at) as last_seen')
            ->groupBy('api_key_hash')
            ->toBase()
            ->get()
            ->map(function (object $row): array {
                /** @var array<string, mixed> $values */
                $values = (array) $row;

                return [
                    'api_key_hash' => is_string($values['api_key_hash'] ?? null) ? $values['api_key_hash'] : null,
                    'entries' => is_numeric($values['entries'] ?? null) ? (int) $values['entries'] : 0,
                    'first_seen' => self::aggregatedAt($values['first_seen'] ?? null),
                    'last_seen' => self::aggregatedAt($values['last_seen'] ?? null),
                ];
            })
            ->values();
    }

    /**
     * An aliased timestamp read back as the app's own clock.
     *
     * An aggregate has no column to inherit a cast from, so it arrives as the
     * driver's own text — with an offset from Postgres' timestamptz and without
     * one from SQLite. Parsing then restating it in the configured zone is what
     * makes the two agree. Each group holds at least one row and `created_at`
     * is never null, so the fallback is unreachable rather than a moment
     * quietly invented for an empty aggregate.
     */
    private static function aggregatedAt(mixed $value): CarbonImmutable
    {
        $moment = is_string($value) ? CarbonImmutable::parse($value) : CarbonImmutable::now();

        return $moment->setTimezone(Config::string('app.timezone'));
    }

    /**
     * Every model name the backlog has seen, for the dashboard's filter.
     *
     * Entries queued without a model are excluded rather than listed as a
     * second blank option indistinguishable from "all models", which is what
     * `non-empty-string` records for anything reading this list.
     *
     * @return Collection<int, non-empty-string>
     */
    public static function distinctModels(): Collection
    {
        return static::query()
            ->whereNotNull('model')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->map(fn (mixed $model): string => is_string($model) ? $model : '')
            ->reject(fn (string $model): bool => $model === '')
            ->values();
    }

    /** When the entry that has been waiting longest was queued. */
    public static function oldestQueuedAt(): ?CarbonImmutable
    {
        return static::query()
            ->where('status', BacklogStatus::Queued)
            ->oldest()
            ->first(['created_at'])?->created_at;
    }

    /**
     * How many queued entries a worker reaches before this one.
     *
     * Ordered by `(created_at, id)` rather than `created_at` alone, the same
     * pair the dashboard pages by: entries queued within the same tick share
     * a timestamp, and a position that cannot break that tie would report two
     * callers as both being third.
     */
    public static function queuedAhead(self $entry): int
    {
        return static::query()
            ->where('status', BacklogStatus::Queued)
            ->where(function (Builder $query) use ($entry): void {
                $query->where('created_at', '<', $entry->created_at)
                    ->orWhere(fn (Builder $query) => $query
                        ->where('created_at', $entry->created_at)
                        ->where('id', '<', $entry->id));
            })
            ->count();
    }

    /** How many entries are waiting, across every caller and gateway. */
    public static function queuedDepth(): int
    {
        return static::query()->where('status', BacklogStatus::Queued)->count();
    }

    /** How many entries a worker is busy with, and so cannot start another for. */
    public static function runningCount(): int
    {
        return static::query()->where('status', BacklogStatus::Running)->count();
    }

    /**
     * How long the last few answers for a model took, newest first.
     *
     * The measurement behind an estimate. Only completed entries are read:
     * a failure's duration is how long the upstream took to give up, which
     * says nothing about how long an answer takes. Of one kind at that: a
     * 64-document embedding batch and a chat completion are not the same unit
     * of work even on the same model, and averaging them together quotes both
     * callers a wait neither will see.
     *
     * @return Collection<int, int>
     */
    public static function recentDurations(?string $model, int $limit, ?BacklogKind $kind = null): Collection
    {
        return static::query()
            ->when($kind, fn (Builder $query, BacklogKind $only) => $query->ofKind($only))
            ->where('status', BacklogStatus::Completed)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->when($model !== null, fn (Builder $query) => $query->where('model', $model))
            ->latest()
            ->limit($limit)
            ->get()
            // Both timestamps are non-null by the query above, so the
            // coalesce is unreachable rather than a silently counted zero.
            ->map(fn (self $entry): int => $entry->durationSeconds() ?? 0)
            ->values();
    }

    /**
     * Entries belong to the key that queued them, so a caller only ever sees
     * their own. A request with no bearer token owns the entries that were
     * likewise queued without one, which is the same rule stated for the
     * degenerate case rather than an exception to it.
     *
     * @param  Builder<BacklogEntry>  $query
     */
    public function scopeOwnedBy(Builder $query, ?string $keyHash): void
    {
        if ($keyHash === null) {
            $query->whereNull('api_key_hash');

            return;
        }

        $query->where('api_key_hash', $keyHash);
    }

    /**
     * Narrow to one endpoint's entries.
     *
     * Every kind now queues, so what separates them is the shape they are
     * collected in: `/v1/responses/{id}` answers with a response object and
     * `/v1/embeddings/{id}` with a vector list. Each poll route reads only
     * its own kind, so an id from one endpoint cannot be collected — or
     * silently mis-shaped — through another.
     *
     * @param  Builder<BacklogEntry>  $query
     */
    public function scopeOfKind(Builder $query, BacklogKind $kind): void
    {
        $query->where('kind', $kind);
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /** @return array<int, string> */
    public function headerNames(): array
    {
        return array_keys($this->headers ?? []);
    }

    /**
     * The caller's own trace id, if they sent one.
     *
     * Readable only while the forwarded headers are still on the entry, so
     * anything logging it has to read it before the entry reaches an ending.
     */
    public function callerRequestId(): ?string
    {
        $id = Arr::get($this->headers ?? [], 'x-request-id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Take ownership of a queued entry, or report that someone else already
     * has. One conditional UPDATE decides it: the database, not the worker,
     * arbitrates, so two workers racing the same entry cannot both proceed
     * and ask the model the same question twice.
     */
    public function claim(): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->where('status', BacklogStatus::Queued)
            ->update([
                'status' => BacklogStatus::Running,
                'attempts' => DB::raw('attempts + 1'),
                'started_at' => now(),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /** @param  array<array-key, mixed>  $completion */
    public function markCompleted(string $text, array $completion): self
    {
        return $this->finish([
            'status' => BacklogStatus::Completed,
            'output' => self::outputFor($text, ToolCalls::fromCompletion($completion)),
            'usage' => self::usageFrom($completion),
            'error' => null,
        ]);
    }

    /**
     * Store an answer that is not a chat message.
     *
     * The upstream's whole body, kept as it arrived: an embedding caller polls
     * for their vectors and has nowhere else to get them, so unlike a chat
     * answer there is nothing here to reduce to text. The dashboard renders
     * `BacklogKind::summarise()` instead of thousands of floats.
     *
     * @param  array<array-key, mixed>  $body
     */
    public function markAnswered(array $body): self
    {
        return $this->finish([
            'status' => BacklogStatus::Completed,
            'output' => $body,
            'usage' => Arr::has($body, 'usage') ? self::usageFrom($body) : null,
            'error' => null,
        ]);
    }

    public function markFailed(Throwable $exception): self
    {
        return $this->finish([
            'status' => BacklogStatus::Failed,
            'error' => UpstreamFailure::describe($exception),
        ]);
    }

    public function requeue(): self
    {
        $this->update(['status' => BacklogStatus::Queued, 'started_at' => null]);

        return $this;
    }

    /**
     * Cancel a queued entry, or report that a worker got to it first.
     *
     * Arbitrated by one conditional UPDATE for the same reason `claim()` is:
     * reading the status, judging it cancellable and then writing leaves a
     * window in which a worker claims the entry, and the `markCompleted()`
     * that follows would land after the cancellation and quietly undo it.
     */
    public function cancelIfQueued(): bool
    {
        $cancelled = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', BacklogStatus::cancellable())
            ->update(self::terminal(['status' => BacklogStatus::Cancelled]));

        if ($cancelled !== 1) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * Move the entry to a state it will not leave.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function finish(array $attributes): self
    {
        $this->update(self::terminal($attributes));

        return $this;
    }

    /**
     * The attributes every ending shares.
     *
     * Each one drops the caller's headers and stamps the finish time, so both
     * live here rather than being restated at each ending: one added later
     * cannot forget to discard the bearer token.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function terminal(array $attributes): array
    {
        return [...$attributes, 'headers' => null, 'finished_at' => now()];
    }

    /** Seconds the entry sat in the queue before a worker picked it up. */
    public function waitedSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        return (int) round($this->created_at->diffInSeconds($this->started_at));
    }

    /** Seconds the upstream call took. */
    public function durationSeconds(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) round($this->started_at->diffInSeconds($this->finished_at));
    }

    /**
     * The answer as text, for anything that shows or downloads one.
     *
     * A chat entry carries the assistant's own message. The other kinds store
     * the upstream's whole body for the caller to collect, so what is shown is
     * a summary of it — but only once there *is* a body: an entry still in the
     * queue has no answer, and summarising its absence would render a waiting
     * embedding as "0 embeddings × 0 dimensions", which reads as an upstream
     * that answered with nothing rather than as one that has not answered.
     */
    public function answerText(): ?string
    {
        if ($this->kind->isChat()) {
            return self::messageText($this->output) ?? ToolCalls::summarise($this->output ?? []);
        }

        return $this->output === null ? null : $this->kind->summarise($this->output);
    }

    /**
     * The assistant's own text, wherever in the output list it sits.
     *
     * Not simply the first item any more: a turn that asked for a tool puts
     * `function_call` items in the same list, and one that asked for nothing
     * else puts them there without a message to lead with.
     *
     * @param  array<array-key, mixed>|null  $output
     */
    private static function messageText(?array $output): ?string
    {
        $message = collect($output ?? [])
            ->first(fn (mixed $item) => JsonValue::stringAt($item, 'type') === 'message');

        $text = data_get($message, 'content.0.text');

        return is_string($text) ? $text : null;
    }

    /** Error message plus the untouched upstream answer, for display. */
    public function errorDetails(): string
    {
        $message = Arr::get($this->error ?? [], 'message');
        $body = Arr::get($this->error ?? [], 'body');

        $text = is_string($message) ? $message : '';

        if (blank($body)) {
            return $text;
        }

        return $text."\n\n".(is_string($body)
            ? $body
            : (string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The Responses API output list: the assistant's message, then one
     * `function_call` item per tool the model asked for.
     *
     * A turn that only asked for tools writes no text, and an empty message
     * item sitting beside the calls would read as an assistant that answered
     * with nothing — so the message is stated only when there is one. With no
     * calls it is stated regardless, because an upstream that genuinely
     * answered with an empty string still owes the caller the shape.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, array<string, mixed>>
     */
    private static function outputFor(string $text, array $toolCalls): array
    {
        $message = [[
            'type' => 'message',
            'role' => 'assistant',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ]];

        if ($toolCalls === []) {
            return $message;
        }

        return [
            ...($text === '' ? [] : $message),
            ...ToolCalls::outputItems($toolCalls),
        ];
    }

    /**
     * What an upstream answer says it cost.
     *
     * The keys an embedding reports are a subset of a completion's — no
     * `completion_tokens` — so the missing one is stored as null rather than
     * as a zero nobody measured.
     *
     * @param  array<array-key, mixed>  $completion
     * @return array<string, int|null>
     */
    private static function usageFrom(array $completion): array
    {
        return [
            'input_tokens' => self::tokens($completion, 'prompt_tokens'),
            'output_tokens' => self::tokens($completion, 'completion_tokens'),
            'total_tokens' => self::tokens($completion, 'total_tokens'),
        ];
    }

    /** @param  array<array-key, mixed>  $completion */
    private static function tokens(array $completion, string $key): ?int
    {
        $value = Arr::get($completion, "usage.{$key}");

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => BacklogStatus::class,
            'kind' => BacklogKind::class,
            'request' => 'array',
            'headers' => 'encrypted:array',
            'output' => 'array',
            'usage' => 'array',
            'error' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'pruned_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
