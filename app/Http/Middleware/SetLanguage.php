<?php

namespace App\Http\Middleware;

use App\Enums\LocaleEnum;
use App\Enums\SessionKeyEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ($request->user()) {
            app()->setLocale($request->user()->locale->value ?? LocaleEnum::DE->value);

            return $next($request);
        }

        if ($request->session()->has(SessionKeyEnum::LANGUAGE->value)) {
            app()->setLocale((string) $request->session()->get(SessionKeyEnum::LANGUAGE->value));

            return $next($request);
        }

        app()->setLocale(LocaleEnum::DE->value);

        return $next($request);
    }
}
