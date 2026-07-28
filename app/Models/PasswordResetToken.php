<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'email';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'token',
    ];

    public function setUpdatedAt(mixed $value): static
    {
        return $this;
    }

    /**
     * Indicate that the model should not use the updated_at column.
     */
    public function getUpdatedAtColumn(): null
    {
        return null;
    }
}
