<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CannotDeactivateDefaultGateway;
use App\Exceptions\CannotRetireDefaultGateway;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;

/**
 * The rules that keep the gateway table in a state the router can rely on:
 * exactly one default, never zero, and never one that has stopped taking work.
 *
 * All three writes go through here rather than through the controllers so the
 * invariant is stated once. Routing reads `Gateway::activeDefault()`, which
 * wants `is_default` *and* `is_active` — so both flags are this service's
 * business, not just the one it is named for.
 */
class Gateways
{
    /**
     * Add a gateway, making it the default when asked or when nothing else
     * holds the flag.
     *
     * The first gateway ever created has to become the default whether or not
     * the operator ticked the box, or every request arriving without an id
     * would 503 until someone noticed. Both writes go in one transaction so
     * two operators creating that first gateway at the same time cannot both
     * claim the flag.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, bool $makeDefault = false): Gateway
    {
        return DB::transaction(function () use ($attributes, $makeDefault): Gateway {
            $gateway = Gateway::create($attributes);

            $claimed = Gateway::query()
                ->where('is_default', true)
                ->whereKeyNot($gateway->getKey())
                ->exists();

            return $claimed && ! $makeDefault ? $gateway : $this->makeDefault($gateway);
        });
    }

    /**
     * Edit a gateway, promoting it if asked.
     *
     * The whole thing is one transaction so a refused deactivation rolls the
     * edit back rather than leaving half of it applied.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws CannotDeactivateDefaultGateway
     */
    public function update(Gateway $gateway, array $attributes, bool $makeDefault = false): Gateway
    {
        return DB::transaction(function () use ($gateway, $attributes, $makeDefault): Gateway {
            $this->refuseDeactivatingTheDefault($gateway, $attributes, $makeDefault);

            $gateway->update($attributes);

            /*
             | Promotion only, never demotion: the table must always hold
             | exactly one default, so clearing the box on the gateway that has
             | it would leave every request without an id nowhere to go. Moving
             | the flag is done by promoting a different gateway, which is why
             | the form disables the box on the one already holding it.
             */
            return $makeDefault && ! $gateway->is_default
                ? $this->makeDefault($gateway)
                : $gateway;
        });
    }

    /**
     * Move the default flag, clearing whichever row holds it now.
     *
     * The gateway is activated in the same breath. Promoting one is an
     * operator saying "send everything here", and a default that has stopped
     * taking work answers 503 rather than answering at all — so a promotion
     * that left `is_active` false would grant a request nobody makes.
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

            $gateway->forceFill(['is_default' => true, 'is_active' => true])->save();
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

    /**
     * Refuse an edit that would leave the default gateway unable to take work.
     *
     * Read from the incoming attributes rather than from the model, because the
     * decision has to be made before the write lands — and because `is_active`
     * is absent entirely from a partial update that leaves it alone, which is
     * a different thing from one that sets it false.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws CannotDeactivateDefaultGateway
     */
    private function refuseDeactivatingTheDefault(Gateway $gateway, array $attributes, bool $makeDefault): void
    {
        $deactivating = array_key_exists('is_active', $attributes) && ! $attributes['is_active'];

        // `$makeDefault` counts too: a gateway being promoted in this same edit
        // is about to hold the flag, so deactivating it lands in the same place.
        if ($deactivating && ($gateway->is_default || $makeDefault)) {
            throw CannotDeactivateDefaultGateway::make();
        }
    }
}
