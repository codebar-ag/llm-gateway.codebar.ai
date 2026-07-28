<?php

use App\Http\Middleware\SetLanguage;
use CodebarAg\LaravelFeaturePolicy\AddFeaturePolicyHeaders;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Csp\AddCspHeaders;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php'
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(RunHealthChecksCommand::class)->everyFiveMinutes();
        $schedule->command('auth:clear-resets')->daily();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Herd/Valet/nginx terminate TLS; trust X-Forwarded-* so the app sees HTTPS and cookies/URLs stay consistent.
        // TRUSTED_PROXIES: `*` (default) trusts headers from the immediate client only (see Illuminate TrustProxies).
        // In production behind a known load balancer, set comma-separated proxy IPs/CIDRs (see .env.example).
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: '*');

        $middleware->web(append: [
            AddCspHeaders::class,
            AddFeaturePolicyHeaders::class,
            SetLanguage::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            // 'permission' => PermissionMiddleware::class,
            // 'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
        $middleware->appendToGroup(group: 'laravel-auth-middleware', middleware: [
            EnsureEmailIsVerified::redirectTo('auth.verification.notice'),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})->create();
