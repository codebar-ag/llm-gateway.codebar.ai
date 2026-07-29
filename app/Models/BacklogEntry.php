<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BacklogStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * @property string $id
 * @property BacklogStatus $status
 * @property string|null $model
 * @property string|null $gateway_id
 * @property Gateway|null $gateway
 * @property array<string, mixed> $request
 * @property array<string, string>|null $headers
 * @property array<int, mixed>|null $output
 * @property array<string, int|null>|null $usage
 * @property array<string, mixed>|null $error
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $api_key_hash
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
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
        'id', 'status', 'model', 'gateway_id', 'request', 'headers', 'output', 'usage',
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
            'output' => self::outputFor($text),
            'usage' => self::usageFrom($completion),
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

    /** @return array<int, array<string, mixed>> */
    private static function outputFor(string $text): array
    {
        return [[
            'type' => 'message',
            'role' => 'assistant',
            'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ]];
    }

    /**
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
            'request' => 'array',
            'headers' => 'encrypted:array',
            'output' => 'array',
            'usage' => 'array',
            'error' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
