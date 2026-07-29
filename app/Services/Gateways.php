<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CannotRetireDefaultGateway;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;

/**
 * The rules that keep the gateway table in a state the router can rely on:
 * exactly one default, and never zero.
 */
class Gateways
{
    /**
     * Add a gateway, making it the default when nothing else holds the flag.
     *
     * The first gateway ever created has to become the default, or every
     * request arriving without an id would 503 until someone noticed. Both
     * writes go in one transaction so two operators creating that first
     * gateway at the same time cannot both claim the flag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Gateway
    {
        return DB::transaction(function () use ($attributes): Gateway {
            $gateway = Gateway::create($attributes);

            $claimed = Gateway::query()
                ->where('is_default', true)
                ->whereKeyNot($gateway->getKey())
                ->exists();

            return $claimed ? $gateway : $this->makeDefault($gateway);
        });
    }

    /**
     * Move the default flag, clearing whichever row holds it now.
     *
     * Both writes go in one transaction because the pgsql partial unique index
     * on `is_default` rejects the intermediate state where two rows claim it.
     */
    public function makeDefault(Gateway $gateway): Gateway
    {
        DB::transaction(function () use ($gateway): void {
            Gateway::query()
                ->where('is_default', true)
                ->whereKeyNot($gateway->getKey())
                ->update(['is_default' => false]);

            $gateway->forceFill(['is_default' => true])->save();
        });

        return $gateway->refresh();
    }

    /**
     * Retire a gateway.
     *
     * Soft delete, so the entries it answered keep their attribution. The
     * default is refused rather than reassigned: which gateway takes over is
     * an operator's decision, not something to guess at.
     *
     * @throws CannotRetireDefaultGateway
     */
    public function delete(Gateway $gateway): void
    {
        if ($gateway->is_default) {
            throw CannotRetireDefaultGateway::make();
        }

        $gateway->delete();
    }
}
