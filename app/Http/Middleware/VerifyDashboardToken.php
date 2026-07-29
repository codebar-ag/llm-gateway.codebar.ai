<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The dashboard renders whole prompts and answers, so it is not public. One
 * shared operator token guards it — a different thing from the caller keys the
 * API replays upstream, which never grant a view of anyone else's entries.
 *
 * The token is presented once as `?token=…` and then remembered in an
 * encrypted cookie, so it stops appearing in URLs, browser history, Referer
 * headers and the dashboard's own filter links. Scripts may send it as a
 * bearer token instead.
 *
 * With no token configured the dashboard does not exist at all.
 */
class VerifyDashboardToken
{
    public const COOKIE = 'dashboard_token';

    public const QUERY = 'token';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = Config::string('gateway.dashboard.token');

        if ($expected === '') {
            throw new NotFoundHttpException('The dashboard is disabled. Set DASHBOARD_TOKEN to enable it.');
        }

        if (self::matches($request->query(self::QUERY), $expected)) {
            return self::remember($request, $expected);
        }

        if (self::matches($request->cookie(self::COOKIE), $expected)
            || self::matches($request->bearerToken(), $expected)) {
            return $next($request);
        }

        throw new AccessDeniedHttpException('A valid dashboard token is required.');
    }

    /**
     * Swap the token in the URL for a cookie, keeping every other filter.
     *
     * Marked `secure` whenever the request arrived over TLS rather than left to
     * `session.secure`: this cookie is the whole dashboard credential, and the
     * framework default for that setting is unset, which would let a browser
     * replay it over plaintext to any host answering the same name.
     */
    private static function remember(Request $request, string $token): RedirectResponse
    {
        return (new RedirectResponse(self::pathWithoutToken($request)))
            ->withCookie(Cookie::make(
                name: self::COOKIE,
                value: $token,
                minutes: self::COOKIE_MINUTES,
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
    }

    private static function pathWithoutToken(Request $request): string
    {
        /** @var array<string, mixed> $parameters */
        $parameters = $request->query->all();

        $query = http_build_query(Arr::except($parameters, self::QUERY));

        return $request->getPathInfo().($query === '' ? '' : '?'.$query);
    }

    private static function matches(mixed $candidate, string $expected): bool
    {
        return is_string($candidate) && hash_equals($expected, $candidate);
    }
}
