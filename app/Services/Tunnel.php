<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TunnelState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads cloudflared's own metrics server. A missing or unreachable tunnel
 * must never take the dashboard down with it, so every failure is an answer
 * rather than an exception.
 */
class Tunnel
{
    private const CACHE_KEY = 'tunnel-state';

    private const CACHE_SECONDS = 10;

    public function state(): TunnelState
    {
        $url = Config::string('gateway.tunnel.metrics_url');

        if ($url === '') {
            return TunnelState::Unknown;
        }

        $cached = Cache::get(self::CACHE_KEY);
        $remembered = is_string($cached) ? TunnelState::tryFrom($cached) : null;

        if ($remembered) {
            return $remembered;
        }

        $state = $this->probe($url);

        Cache::put(self::CACHE_KEY, $state->value, self::CACHE_SECONDS);

        return $state;
    }

    private function probe(string $url): TunnelState
    {
        try {
            $ready = Http::timeout(Config::integer('gateway.tunnel.timeout'))
                ->connectTimeout(Config::integer('gateway.tunnel.timeout'))
                ->get(Str::of($url)->rtrim('/')->append('/ready')->value())
                ->successful();
        } catch (Throwable) {
            return TunnelState::Down;
        }

        if (! $ready) {
            return TunnelState::Down;
        }

        return TunnelState::Ready;
    }
}
