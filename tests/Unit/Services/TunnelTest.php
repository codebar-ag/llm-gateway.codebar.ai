<?php

declare(strict_types=1);

use App\Enums\TunnelState;
use App\Services\Tunnel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config(['gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241']);
    $this->tunnel = new Tunnel;
});

it('reports unknown when no metrics endpoint is configured', function () {
    config(['gateway.tunnel.metrics_url' => '']);
    Http::fake();

    expect($this->tunnel->state())->toBe(TunnelState::Unknown);

    Http::assertNothingSent();
});

it('reports ready when cloudflared answers', function () {
    Http::fake(['127.0.0.1:20241/ready' => Http::response('OK', 200)]);

    expect($this->tunnel->state())->toBe(TunnelState::Ready);
});

it('asks the ready endpoint of the configured url', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->tunnel->state();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:20241/ready');
});

it('tolerates a trailing slash in the configured url', function () {
    config(['gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241/']);
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->tunnel->state();

    Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:20241/ready');
});

it('reports down when cloudflared answers with an error', function (int $status) {
    Http::fake(['*' => Http::response('', $status)]);

    expect($this->tunnel->state())->toBe(TunnelState::Down);
})->with([503, 500, 404]);

it('reports down when nothing is listening', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect($this->tunnel->state())->toBe(TunnelState::Down);
});

it('never lets a broken tunnel throw', function () {
    Http::fake(fn () => throw new RuntimeException('anything at all'));

    expect($this->tunnel->state())->toBe(TunnelState::Down);
});

it('caches the answer so a refreshing dashboard does not hammer cloudflared', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->tunnel->state();
    $this->tunnel->state();
    $this->tunnel->state();

    expect(Http::recorded())->toHaveCount(1);
});

it('probes again once the cache expires', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $this->tunnel->state();
    $this->travel(11)->seconds();
    $this->tunnel->state();

    expect(Http::recorded())->toHaveCount(2);
});

it('probes again when the cached value is unusable', function () {
    Cache::put('tunnel-state', ['not', 'a', 'state'], 60);
    Http::fake(['*' => Http::response('OK', 200)]);

    expect($this->tunnel->state())->toBe(TunnelState::Ready)
        ->and(Http::recorded())->toHaveCount(1);
});
