<?php

declare(strict_types=1);

use App\Support\UpstreamFailure;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

function requestException(mixed $body, int $status): RequestException
{
    Http::fake(['upstream.test/*' => Http::response($body, $status)]);

    return new RequestException(Http::get('upstream.test/x'));
}

it('describes plain exceptions with class and message', function () {
    expect(UpstreamFailure::describe(new RuntimeException('boom')))->toBe([
        'type' => 'exception',
        'message' => 'RuntimeException: boom',
    ]);
});

it('passes a json error body through unchanged', function () {
    $failure = UpstreamFailure::describe(requestException([
        'error' => ['message' => 'Invalid model name passed in model=nope:1b', 'code' => '400'],
    ], 400));

    expect($failure['type'])->toBe('upstream')
        ->and($failure['status'])->toBe(400)
        ->and($failure['message'])->toBe('Upstream responded with HTTP 400')
        ->and($failure['body'])->toBe([
            'error' => ['message' => 'Invalid model name passed in model=nope:1b', 'code' => '400'],
        ]);
});

it('keeps a non json body as raw text', function () {
    $failure = UpstreamFailure::describe(requestException('502 Bad Gateway', 502));

    expect($failure['status'])->toBe(502)
        ->and($failure['body'])->toBe('502 Bad Gateway');
});

it('never truncates long bodies', function () {
    $long = str_repeat('x', 5000);

    $failure = UpstreamFailure::describe(requestException($long, 500));

    expect($failure['body'])->toHaveLength(5000);
});

it('does not truncate the exception message either', function () {
    expect(UpstreamFailure::describe(new RuntimeException(str_repeat('y', 3000)))['message'])
        ->toHaveLength(3000 + strlen('RuntimeException: '));
});

it('ignores the client request object', function () {
    $failure = UpstreamFailure::describe(requestException(['error' => 'nope'], 404));

    expect($failure)->not->toHaveKey('request')
        ->and(Http::recorded()[0][0])->toBeInstanceOf(Request::class)
        ->and(Http::recorded()[0][1])->toBeInstanceOf(Response::class);
});
