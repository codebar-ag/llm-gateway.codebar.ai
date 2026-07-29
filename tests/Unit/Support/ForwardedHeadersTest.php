<?php

declare(strict_types=1);

use App\Support\ForwardedHeaders;
use Illuminate\Http\Request;

function headerRequest(array $headers = []): Request
{
    $request = Request::create('/v1/chat/completions', 'POST');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

it('keeps the caller headers as they arrived', function () {
    $forwarded = ForwardedHeaders::from(headerRequest([
        'Authorization' => 'Bearer sk-caller',
        'Content-Type' => 'application/json',
        'X-Litellm-Tag' => 'invoices',
    ]));

    expect($forwarded['authorization'])->toBe('Bearer sk-caller')
        ->and($forwarded['content-type'])->toBe('application/json')
        ->and($forwarded['x-litellm-tag'])->toBe('invoices');
});

it('forwards the headers the upstream actually reads', function (string $header, string $value) {
    expect(ForwardedHeaders::from(headerRequest([$header => $value])))
        ->toHaveKey(strtolower($header), $value);
})->with([
    ['Authorization', 'Bearer sk-caller'],
    ['Content-Type', 'application/json'],
    ['Accept', 'application/json'],
    ['Accept-Language', 'de-CH'],
    ['User-Agent', 'curl/8.7.1'],
    ['X-Request-Id', 'abc-123'],
]);

it('forwards vendor prefixed headers so tagging keeps working', function (string $header) {
    expect(ForwardedHeaders::from(headerRequest([$header => 'invoices'])))
        ->toHaveKey(strtolower($header), 'invoices');
})->with(['X-Litellm-Tag', 'X-Litellm-Anything-At-All', 'OpenAI-Beta', 'Anthropic-Version']);

it('never forwards a header the upstream has no business seeing', function (string $header) {
    expect(ForwardedHeaders::from(headerRequest([$header => 'whatever'])))
        ->not->toHaveKey(strtolower($header));
})->with([
    'Cookie', 'Set-Cookie', 'X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto',
    'X-Real-Ip', 'Referer', 'Origin', 'X-Csrf-Token', 'X-Completely-Made-Up',
]);

it('does not leak the dashboard session cookie upstream', function () {
    $request = headerRequest(['Authorization' => 'Bearer sk-caller']);
    $request->headers->set('Cookie', 'laravel_session=abc; dashboard_token=secret');

    $forwarded = ForwardedHeaders::from($request);

    expect($forwarded)->not->toHaveKey('cookie')
        ->and(implode(' ', $forwarded))->not->toContain('dashboard_token')
        ->and($forwarded['authorization'])->toBe('Bearer sk-caller');
});

it('drops the headers that describe this hop', function (string $header) {
    $forwarded = ForwardedHeaders::from(headerRequest([$header => 'whatever']));

    expect($forwarded)->not->toHaveKey(strtolower($header));
})->with([
    'Host', 'Content-Length', 'Connection', 'Keep-Alive', 'Transfer-Encoding',
    'Upgrade', 'Proxy-Authenticate', 'Proxy-Authorization', 'TE', 'Trailer', 'Expect',
]);

it('joins repeated headers into one value', function () {
    $request = headerRequest();
    $request->headers->set('X-Litellm-Tag', 'one', false);
    $request->headers->set('X-Litellm-Tag', 'two', false);

    expect(ForwardedHeaders::from($request)['x-litellm-tag'])->toBe('one, two');
});

it('hashes the bearer token for attribution', function () {
    expect(ForwardedHeaders::keyHash(headerRequest(['Authorization' => 'Bearer sk-caller'])))
        ->toBe(hash('sha256', 'sk-caller'));
});

it('reports no hash without an authorization header', function () {
    expect(ForwardedHeaders::keyHash(headerRequest()))->toBeNull();
});

it('reports no hash for a non bearer authorization', function () {
    expect(ForwardedHeaders::keyHash(headerRequest(['Authorization' => 'Basic dXNlcjpwdw=='])))->toBeNull();
});

it('returns headers even when nothing but defaults were sent', function () {
    expect(ForwardedHeaders::from(headerRequest()))->toBeArray();
});
