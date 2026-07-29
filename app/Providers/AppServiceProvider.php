<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\ForwardedHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // The OpenAI shape has no envelope; the list endpoint builds its own.
        JsonResource::withoutWrapping();

        // Herd, Valet, nginx and Caddy terminate TLS in front of the app.
        TrustProxies::at(Config::string('gateway.trusted_proxies'));

        // Upstream answers are stored verbatim; Laravel would cut the body
        // off at 120 characters and hide why LiteLLM rejected a request.
        RequestException::dontTruncate();

        $this->limitQueueing();
    }

    /**
     * A queued entry costs a row and a worker slot before anything upstream
     * has seen it, so the number of them one caller may create is capped.
     * The payload is still never inspected — only how often it arrives.
     */
    private function limitQueueing(): void
    {
        RateLimiter::for('queue', function (Request $request) {
            $perMinute = Config::integer('gateway.backlog.rate_limit');

            if ($perMinute <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($perMinute)->by(self::caller($request));
        });
    }

    private static function caller(Request $request): string
    {
        return ForwardedHeaders::keyHash($request) ?? 'ip:'.($request->ip() ?? 'unknown');
    }
}
