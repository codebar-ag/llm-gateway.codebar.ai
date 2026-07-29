<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveGateway;
use App\Models\Gateway;
use Illuminate\Http\Request;

/*
 | Unreachable through the router — nothing gets to a controller without the
 | middleware having put a gateway on the request — so this asserts the guard
 | that would otherwise be the one untested line standing between a
 | misconfiguration and a null dereference deep in a driver.
 */

it('refuses to name a gateway for a request that never went through the middleware', function () {
    expect(fn () => ResolveGateway::of(Request::create('/v1/chat/completions', 'POST')))
        ->toThrow(RuntimeException::class, 'No gateway was resolved');
});

it('hands back the gateway the middleware resolved', function () {
    $gateway = Gateway::factory()->create();

    $request = Request::create('/v1/chat/completions', 'POST');
    $request->attributes->set(ResolveGateway::ATTRIBUTE, $gateway);

    expect(ResolveGateway::of($request)->id)->toBe($gateway->id);
});
