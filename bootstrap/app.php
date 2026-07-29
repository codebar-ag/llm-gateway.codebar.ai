<?php

declare(strict_types=1);

use App\Http\Middleware\RequireCallerKey;
use App\Http\Middleware\ResolveGateway;
use App\Http\Middleware\VerifyDashboardToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 | Two shapes, because a gateway id may sit in front of the API: `v1/…` at the
 | root, and `<uuid>/v1/…` when a caller addresses one gateway in particular.
 | Matching only the first would serve HTML error pages, and Laravel's bare
 | throttle message, to every prefixed caller.
 */
$isApi = fn (Request $request): bool => $request->is('v1/*', '*/v1/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        health: '/up',
    )
    // The api group has no auth of its own, since this app only queues what it
    // is handed. The dashboard is a different matter and carries its own guard.
    // `gateway` resolves which upstream a request is addressed to.
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'dashboard' => VerifyDashboardToken::class,
            'gateway' => ResolveGateway::class,
        ]);

        // Appended to the whole group rather than named per route: whether a
        // key is required is one deployment-wide answer, and it has to hold for
        // reading an entry back just as much as for queueing one.
        $middleware->api(append: [RequireCallerKey::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isApi) {
        $exceptions->shouldRenderJsonWhen($isApi);

        // findOrFail is already a NotFoundHttpException by the time render
        // callbacks run, so match on what it was raised from rather than on
        // every 404 the router produces.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request) || ! $e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json([
                'error' => [
                    'message' => 'No entry with id '.$request->route('id'),
                    'type' => 'not_found',
                ],
            ], 404);
        });

        // Same error envelope the rest of the API uses, rather than Laravel's
        // bare "Too Many Attempts." message.
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $isApi($request)
            ? response()->json([
                'error' => [
                    'message' => 'Too many queued requests, the limit is '
                        .Config::integer('gateway.backlog.rate_limit')
                        .' per minute. See the Retry-After header.',
                    'type' => 'rate_limited',
                ],
            ], 429, $e->getHeaders())
            : null);
    })->create();
