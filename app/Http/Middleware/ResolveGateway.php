<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Gateway;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which upstream a request is addressed to.
 *
 * A caller picks one by putting its id before /v1:
 *
 *     POST /9f3c…/v1/chat/completions   → that gateway
 *     POST /v1/chat/completions         → whichever gateway is the default
 *
 * Resolved here rather than by route model binding, because the same answer
 * has to be reached for a route that has no {gateway} parameter at all, and
 * because an unknown id must read as a 404 for the path rather than for a
 * model the caller never named.
 */
class ResolveGateway
{
    public const ATTRIBUTE = 'gateway';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->route(self::ATTRIBUTE);

        $gateway = is_string($id) ? self::find($id) : Gateway::activeDefault();

        if (! $gateway instanceof Gateway) {
            return self::unresolved(is_string($id));
        }

        $request->attributes->set(self::ATTRIBUTE, $gateway);

        /*
         | Drop the parameter now that it has been read. Laravel hands route
         | parameters to a controller positionally, so leaving it in place
         | would put the gateway id into `ShowResponseController`'s $id and
         | every other single-parameter action. Controllers are then identical
         | either side of the prefix, which is the point.
         */
        $request->route()?->forgetParameter(self::ATTRIBUTE);

        return $next($request);
    }

    /** The gateway a request is being handled for. */
    public static function of(Request $request): Gateway
    {
        $gateway = $request->attributes->get(self::ATTRIBUTE);

        if (! $gateway instanceof Gateway) {
            // Unreachable through the router: nothing reaches a controller
            // without this middleware having put a gateway on the request.
            throw new RuntimeException('No gateway was resolved for this request.');
        }

        return $gateway;
    }

    /**
     * Whether the caller named a gateway rather than taking the default.
     *
     * Read from the route name because the parameter itself is gone by the
     * time a controller runs — see the note in `handle()`.
     */
    public static function wasAddressed(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'gateway.');
    }

    /** Inactive gateways are invisible rather than rejected: they stop taking new work. */
    private static function find(string $id): ?Gateway
    {
        return Gateway::query()->active()->whereKey($id)->first();
    }

    private static function unresolved(bool $addressed): Response
    {
        return $addressed
            ? response()->json([
                'error' => [
                    'message' => 'No such gateway. Check the id in front of /v1, or drop it to use the default.',
                    'type' => 'gateway_not_found',
                ],
            ], 404)
            : response()->json([
                'error' => [
                    'message' => 'No active default gateway is configured. Set one in the dashboard at /gateways.',
                    'type' => 'no_default_gateway',
                ],
            ], 503);
    }
}
