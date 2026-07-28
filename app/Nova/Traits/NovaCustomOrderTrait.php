<?php

namespace App\Nova\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;

trait NovaCustomOrderTrait
{
    /**
     * @param  array<string, string>  $orderings
     */
    protected static function applyOrderings(Builder $query, array $orderings): Builder
    {
        if (empty($orderings) && static::$orderBy !== []) {
            $query->reorder();
            $orderings = static::$orderBy;
        }

        return parent::applyOrderings($query, $orderings);
    }
}
