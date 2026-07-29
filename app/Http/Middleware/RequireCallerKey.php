<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ForwardedHeaders;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optionally refuses callers that bring no key.
 *
 * Ownership of an entry is the sha256 of the bearer token that queued it, so
 * every keyless caller shares one bucket: any of them can read, cancel and
 * delete the others' entries. That is the documented rule stated for its
 * degenerate case rather than a bug, and it is harmless on a trusted LAN — but
 * it means the boundary is drawn by "sent no token", which is no boundary at
 * all on anything reachable.
 *
 * This still authenticates nothing. Whether a key is any *good* stays the
 * upstream's decision; all that is checked here is that one was sent, so the
 * entries it queues belong to somebody in particular.
 */
class RequireCallerKey
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Config::boolean('gateway.api.require_caller_key')) {
            return $next($request);
        }

        if (ForwardedHeaders::keyHash($request) !== null) {
            return $next($request);
        }

        return response()->json([
            'error' => [
                'message' => 'This gateway requires a key. Send it as `Authorization: Bearer …` — it is replayed upstream, and it is what decides which entries are yours.',
                'type' => 'no_caller_key',
            ],
        ], 401);
    }
}
