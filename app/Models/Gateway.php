<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayProtocol;
use Carbon\CarbonImmutable;
use Database\Factories\GatewayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * An upstream this app can hand a request to.
 *
 * The primary key is the URL segment a caller puts before /v1, so a gateway
 * is addressed by knowing its id and nothing else. It carries no credentials:
 * the caller's own Authorization is still what authenticates upstream.
 *
 * @property string $id
 * @property string $name
 * @property GatewayProtocol $protocol
 * @property string $base_url
 * @property string|null $path
 * @property string|null $default_model
 * @property int $timeout
 * @property int $connect_timeout
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $description
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<Gateway> query()
 */
class Gateway extends Model
{
    /** @use HasFactory<GatewayFactory> */
    use HasFactory;

    use HasUuids, SoftDeletes;

    protected $table = 'gateways';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'name', 'protocol', 'base_url', 'path', 'default_model',
        'timeout', 'connect_timeout', 'is_default', 'is_active', 'description',
    ];

    /**
     * The gateway holding the default flag, whether or not it still accepts work.
     *
     * This is the operator's view of the table. Routing wants `activeDefault()`.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * The gateway a request without a UUID prefix is sent to.
     *
     * Deactivating a gateway has to stop new work reaching it whether callers
     * address it by id or fall through to the default, so this applies the
     * same `active()` scope the prefixed lookup does.
     */
    public static function activeDefault(): ?self
    {
        return static::query()->active()->where('is_default', true)->first();
    }

    /** @param  Builder<Gateway>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<BacklogEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(BacklogEntry::class);
    }

    /** Full URL the driver posts to. */
    public function endpoint(): string
    {
        return Str::of($this->base_url)
            ->rtrim('/')
            ->append($this->path ?: $this->protocol->defaultPath())
            ->value();
    }

    /** What a caller puts in their SDK's base_url to address this gateway. */
    public function callerBaseUrl(): string
    {
        return rtrim(Config::string('app.url'), '/').'/'.$this->id.'/v1';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'protocol' => GatewayProtocol::class,
            'timeout' => 'integer',
            'connect_timeout' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
