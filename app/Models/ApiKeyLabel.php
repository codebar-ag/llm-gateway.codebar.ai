<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ApiKeyLabelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A name for the key hash a caller's entries carry.
 *
 * Nothing here authenticates anyone: the label is a reading aid, so the
 * dashboard can say "Nightly batch" where it would otherwise print 64 hex
 * characters. Labelling a hash and revoking a key are unrelated acts, which is
 * why this table holds no key material and has no relation to one.
 *
 * @property string $id
 * @property string $api_key_hash
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 *
 * @method static Builder<ApiKeyLabel> query()
 */
class ApiKeyLabel extends Model
{
    /** @use HasFactory<ApiKeyLabelFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The hash standing for entries queued without a bearer token.
     *
     * A sentinel string rather than NULL: unique indexes treat NULLs as
     * distinct on both Postgres and SQLite, so a nullable column could hold two
     * labels for the same keyless caller. No real hash can collide with it —
     * a sha256 digest is 64 characters of [0-9a-f].
     */
    public const ANONYMOUS = 'anonymous';

    protected $table = 'api_key_labels';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['api_key_hash', 'name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
