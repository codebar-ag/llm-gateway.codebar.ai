<p align="center">
<a href="https://github.com/codebar-ag/laravel-start" target="_blank">
<img src="https://banners.beyondco.de/Laravel%20Start.png?theme=dark&pattern=architect&style=style_1&description=codebar+Solutions+AG&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg" alt="Laravel Start">
</a>
</p>

## About Laravel Start

**Laravel Start** is an application template for Codebar Solutions AG Laravel projects (PHP **8.5**, Laravel **13**). It is a starting point for new applications and includes common packages, security defaults, and tooling.

**Runtime (`composer.json` `require`):**

- [Laravel Framework](https://laravel.com/docs) 13.x
- [Laravel Nova](https://nova.laravel.com)
- [Laravel Nightwatch](https://nightwatch.laravel.com) (production telemetry; `NIGHTWATCH_*`, supervised `php artisan nightwatch:agent`, `LOG_STACK` including `nightwatch`)
- [Laravel Tinker](https://github.com/laravel/tinker)
- [codebar-ag/laravel-microsoft-entra-sso](https://github.com/codebar-ag/laravel-microsoft-entra-sso) (Microsoft Entra ID / Azure AD SSO)
- [codebar-ag/laravel-flysystem-cloudinary](https://github.com/codebar-ag/laravel-flysystem-cloudinary) and [codebar-ag/laravel-flysystem-cloudinary-nova](https://github.com/codebar-ag/laravel-flysystem-cloudinary-nova)
- [league/flysystem-aws-s3-v3](https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/) (S3-compatible storage, Lasso bundles)
- [Sammyjo Lasso](https://github.com/Sammyjo20/lasso) (asset bundles via [`config/lasso.php`](config/lasso.php) on the [`s3`](config/filesystems.php) disk; production publish in GitHub Actions—see [Production assets (Lasso)](#production-assets-lasso))
- [Spatie Laravel Activity Log](https://github.com/spatie/laravel-activitylog)
- [Spatie Laravel CSP](https://github.com/spatie/laravel-csp) (preset [`App\Security\SecurityPolicyBasic`](app/Security/SecurityPolicyBasic.php); allowlists in [`config/csp-allowlists.json`](config/csp-allowlists.json), wiring in [`config/csp.php`](config/csp.php))
- [Spatie Laravel Flash](https://github.com/spatie/laravel-flash) (flash messages in layouts)
- [Spatie Laravel Health](https://github.com/spatie/laravel-health) plus [Spatie Security Advisories Health Check](https://github.com/spatie/security-advisories-health-check)
- [Spatie Laravel Honeypot](https://github.com/spatie/laravel-honeypot) (spam protection on auth POST routes)
- [Spatie Laravel Permission](https://github.com/spatie/laravel-permission)
- [Spatie Laravel Sitemap](https://github.com/spatie/laravel-sitemap) (`php artisan sitemap:generate`)
- [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html) and [Symfony Postmark Mailer](https://symfony.com/doc/current/mailer.html#using-a-3rd-party-transport)

**Frontend:** Blade views, [Vite](https://vitejs.dev/) **7**, [Tailwind CSS](https://tailwindcss.com/) **4**, [Alpine.js](https://alpinejs.dev/) **3** (see [`package.json`](package.json)). This template does not ship Inertia, Fortify, or Livewire in Composer.

**Development tooling:**

- [codebar-ag/coding-guidelines](https://github.com/codebar-ag/coding-guidelines) and [Laravel Boost](https://github.com/laravel/boost) (see [AI-assisted development](#ai-assisted-development))
- [Pest](https://pestphp.com/) 4, [Laravel Dusk](https://laravel.com/docs/dusk) 8, [Larastan](https://github.com/larastan/larastan) / PHPStan, [Laravel Pint](https://github.com/laravel/pint), [Laravel Sail](https://laravel.com/docs/sail)

## Codebase map

- [`routes/web.php`](routes/web.php) — authenticated dashboard (`start.index`); includes [`routes/auth.php`](routes/auth.php)
- [`app/Http/Controllers/Auth/`](app/Http/Controllers/Auth/) — session login, password reset, email verification
- [`app/Nova/`](app/Nova/) — Nova resources, policies, dashboards
- [`app/Security/`](app/Security/) — [`SecurityPolicyBasic`](app/Security/SecurityPolicyBasic.php) (CSP), [`FeaturePolicyBasic`](app/Security/FeaturePolicyBasic.php) (Permissions-Policy; uses `codebar-ag/laravel-feature-policy`)
- Permissions-Policy middleware and builder ship in the [`codebar-ag/laravel-feature-policy`](https://packagist.org/packages/codebar-ag/laravel-feature-policy) Composer package
- [`app/Listeners/Auth/`](app/Listeners/Auth/) — SSO provisioning, Microsoft profile sync, login/logout hooks
- [`app/Console/Commands/`](app/Console/Commands/) — sitemap, admin grants, Entra migration helpers
- [`config/microsoft-entra-sso.php`](config/microsoft-entra-sso.php) — Entra ID OAuth settings
- [`tests/Feature`](tests/Feature), [`tests/Unit`](tests/Unit), [`tests/Browser`](tests/Browser) — Pest feature/unit tests and Dusk browser tests

## Installation

You can start from this repository with **Use this template** on GitHub, or clone it directly.

```bash
composer install

cp .env.example .env

php artisan key:generate

php artisan boost:install
php artisan boost:update

php artisan migrate --seed
```

This project expects **Node 24 or newer** (see [`package.json`](package.json) `engines`).

```bash
npm install
npm run build
```

If you want to use Valet to serve your application, you can run the following command:

```bash
valet link

valet secure

valet open
```

If you want to use Herd to serve your application, you can run the following command:

```bash
herd link

herd secure

herd open
```

You can run the development asset server with the following command:

```bash
npm run dev
```

> Note: You should set `valetTls: 'your-domain.test',` below `refresh: true,` in your [`vite.config.js`](vite.config.js) file if you use
> `valet secure` or `herd secure`.

> **Reverse proxies:** [`bootstrap/app.php`](bootstrap/app.php) configures trusted proxies from `TRUSTED_PROXIES` (default `*`), so `X-Forwarded-*` matches TLS-terminated local URLs. In production, set `TRUSTED_PROXIES` to your load balancer or ingress IPs/CIDRs (comma-separated); see Laravel’s trusted proxies documentation.

## AI-assisted development

The template includes **`codebar-ag/coding-guidelines`** as a dev dependency (wired via the path repository `packages/coding-guidelines`, which tracks [codebar-ag/coding-guidelines](https://github.com/codebar-ag/coding-guidelines) with Laravel 13–compatible `illuminate/*` constraints). That package ships `RULES.md` and Boost skills under `resources/boost/skills/`. See the root **[`AGENTS.md`](AGENTS.md)** for paths, agent roles (ArchitectAgent, ReviewAgent, etc.), and how to point Claude or other assistants at the canonical rules.

**Repository note:** Generated Boost and editor assets are not committed—`.claude/`, `.cursor/`, `.junie/`, root `.mcp.json`, and `CLAUDE.md` are listed in `.gitignore`. Canonical skill sources live under `vendor/codebar-ag/coding-guidelines/resources/boost/skills/` (and optional project overrides under `.ai/skills/`). After every clone you must run Boost below so Cursor, Claude Code, Junie, and MCP get local copies of skills and config.

After `composer install`, set up and refresh Boost (Laravel only registers Boost when the app is not in the PHPUnit/testing bootstrap and the environment is **local** or **`APP_DEBUG` is true**, so use a normal local `.env` from `.env.example`, or prefix commands as shown):

```bash
php artisan boost:install
php artisan boost:update
```

When your `.env` uses `APP_ENV=testing` (for example in some automated setups), run:

```bash
APP_ENV=local php artisan boost:install
APP_ENV=local php artisan boost:update
```

**Example prompt (ReviewAgent):** “Act as **ReviewAgent**. Using `RULES.md` and all skills under `resources/boost/skills/**/SKILL.md` from codebar-ag/coding-guidelines, review this diff and produce: (1) a short assessment, (2) a file-grouped refactor plan, (3) a few copy-pasteable suggestions.”

Optional CI and MCP setup are described in the [coding-guidelines README](https://github.com/codebar-ag/coding-guidelines/blob/main/README.md).

## Assets

Assets should be set in the following directories:

- `resources/js` for JavaScript files.
- `resources/css` for CSS files.
- `resources/fonts` for Font files.
- `resources/images` for Image files.

After you have added your assets, you can run the following command to compile them:

```bash
npm run build
```

To include your assets in your blade files, you can use the following:

```blade
{{ Vite::asset('resources/images/your-image.png') }}
```

## Auth

Session authentication is enabled by default. Guards and providers live in [`config/auth.php`](config/auth.php). HTTP routes are defined in [`routes/auth.php`](routes/auth.php) (prefix `auth`, named routes `auth.*`) and implemented by controllers under [`app/Http/Controllers/Auth/`](app/Http/Controllers/Auth/). Sensitive POST routes use throttling and [Spatie Honeypot](https://github.com/spatie/laravel-honeypot) (`ProtectAgainstSpam`).

### Dashboard

After login, users with the `user` role land on the **dashboard** (`/`, route name `start.index`). The Nova admin link is shown only when `Gate::allows('viewNova')` (administrators by default). Microsoft SSO creates or links a `User`, assigns the `user` role, stores OAuth tokens on `microsoft_sso_identities` (encrypted at rest via the Entra SSO package), and marks the email verified for that IdP login.

### Email verification

If you wish to use email verification, you can use the following middleware to protect your routes:

```php
Route::middleware(['laravel-auth-middleware'])->group(function () {
    ...
});
```

The `laravel-auth-middleware` group is registered in [`bootstrap/app.php`](bootstrap/app.php) and includes `EnsureEmailIsVerified` with a redirect to `auth.verification.notice`.

This template already adds `laravel-auth-middleware` to Nova in [`config/nova.php`](config/nova.php) so verified email is required for the admin panel. If you remove it, restore it like this:

```php
'middleware' => [
    'web',
    'laravel-auth-middleware',
    \Laravel\Nova\Http\Middleware\HandleInertiaRequests::class,
    'nova:serving',
],
```

### Microsoft Entra ID (Azure AD) SSO

SSO is provided by **`codebar-ag/laravel-microsoft-entra-sso`**. Configure [`config/microsoft-entra-sso.php`](config/microsoft-entra-sso.php) via environment variables (see [`.env.example`](.env.example)):

- `MICROSOFT_ENTRA_SSO_TENANT_ID`
- `MICROSOFT_ENTRA_SSO_CLIENT_ID`
- `MICROSOFT_ENTRA_SSO_CLIENT_SECRET`
- `MICROSOFT_ENTRA_SSO_REDIRECT_URI` (default in `.env.example`: `${APP_URL}/sso/microsoft/web/callback`)

For local development, `APP_URL` must be reachable by Microsoft (use [expose](https://expose.dev/) or [ngrok](https://ngrok.com/)) and the redirect URI in Entra must match exactly.

```bash
APP_URL=https://your-expose-or-ngrok-url.example

MICROSOFT_ENTRA_SSO_REDIRECT_URI="${APP_URL}/sso/microsoft/web/callback"
```

## Permissions

Please refer to the [Spatie Permission](https://github.com/spatie/laravel-permission) documentation for more information
on how to use permissions.

## Enums

Enums are included in PHP's core functionality but we have some additional functionality to make them easier to use.

You can create an enum in the `app/Enums` directory:

```php
<?php

namespace App\Enums;

use App\Traits\HasNovaEnumLabelTrait;

enum EnvironmentEnum: string
{
    use HasNovaEnumLabelTrait;

    case PRODUCTION = 'production';
    case STAGING = 'staging';
    case LOCAL = 'local';

    public function getLabel(): string
    {
        return match ($this) {
            EnvironmentEnum::PRODUCTION => __('Production'),
            EnvironmentEnum::STAGING => __('Staging'),
            EnvironmentEnum::LOCAL => __('Local'),
        };
    }
}
```

You should use the `HasNovaEnumLabelTrait` trait to add label functionality to your enum.
The trait provides both `getLabel()` and `label()` methods, with `label()` being an alias for Nova integration.

The `label` method should return the label for the enum value.

You can use the enum in your code like this:

```php
// Native PHP Enum
$enum = EnvironmentEnum::PRODUCTION; // Enum Object
$name = EnvironmentEnum::PRODUCTION->name; // Enum Name (PRODUCTION)
$value = EnvironmentEnum::PRODUCTION->value; // Enum Value (production)

// Label
$label = EnvironmentEnum::PRODUCTION->label();  // Enum Label using Laravels Translation (Production)

// Labels
$labels = EnvironmentEnum::labels(); // Array of Enum Labels with Enum value as Key (['production' => 'Production', 'staging' => 'Staging', 'local' => 'Local'])
```

## Health

This app runs `spatie/laravel-health` checks on a **schedule** (`RunHealthChecksCommand` every five minutes). Individual checks may still define their own intervals (for example `everyFiveMinutes()` in [`AppServiceProvider`](app/Providers/AppServiceProvider.php)). In production, add a cron entry:

```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Alerts use **[Oh Dear](https://ohdear.app/)**: enable `OH_DEAR_HEALTH_CHECK_ENABLED`, set `OH_DEAR_HEALTH_CHECK_SECRET` to match Oh Dear, and configure the monitor URL as `APP_URL` + `OH_DEAR_HEALTH_CHECK_PATH` (default `/oh-dear-health-check-results`). Oh Dear sends the `oh-dear-health-check-secret` header automatically.

Please refer to the [Spatie Health](https://github.com/spatie/laravel-health) documentation for more information on how
to use health checks.

Registered checks in [`AppServiceProvider`](app/Providers/AppServiceProvider.php) include Spatie’s `DebugModeCheck`, `CacheCheck`, `OptimizedAppCheck`, `EnvironmentCheck` (production only), **`SecurityAdvisoriesCheck`** ([spatie/security-advisories-health-check](https://github.com/spatie/security-advisories-health-check), last day of month), and custom checks in [`app/Checks`](app/Checks):

- `JobsCheck`
- `FailedJobsCheck`

## Helpers

We have added some helper functions which are located in the `app/Helpers` directory.

You should use the Facades for the helpers which are located in the `app/Helpers/Facades` directory:

- `HelperBank`
- `HelperDate`
- `HelperDevice`
- `HelperFile`
- `HelperMarkdown`
- `HelperMoney`
- `HelperNumber`
- `HelperPhone`

## Laravel Nightwatch

[Laravel Nightwatch](https://nightwatch.laravel.com) is part of this template’s **production** story. Set `NIGHTWATCH_TOKEN` from your Nightwatch dashboard, run the ingest agent (`php artisan nightwatch:agent`) under process supervision wherever the app runs, and keep `LOG_STACK` including `nightwatch` (see `.env.example`). Use `NIGHTWATCH_ENABLED=false` only when the agent is intentionally not running (for example some local setups). Configuration defaults load from the package; run `php artisan vendor:publish --tag=nightwatch-config` when you need a project-local `config/nightwatch.php`.

## Security headers

**Content-Security-Policy** is applied by [Spatie Laravel CSP](https://github.com/spatie/laravel-csp). The active preset is [`App\Security\SecurityPolicyBasic`](app/Security/SecurityPolicyBasic.php), configured in [`config/csp.php`](config/csp.php). Third-party source lists are merged from [`config/csp-allowlists.json`](config/csp-allowlists.json) via [`App\Support\CspAllowlist`](app/Support/CspAllowlist.php). Toggle with `CSP_ENABLED` (see `.env.example`). Middleware is registered in [`bootstrap/app.php`](bootstrap/app.php) (`AddCspHeaders`).

**Permissions-Policy** (legacy filename [`config/feature-policy.php`](config/feature-policy.php)) is implemented by [`codebar-ag/laravel-feature-policy`](https://packagist.org/packages/codebar-ag/laravel-feature-policy) (`AddFeaturePolicyHeaders` and related types). The app policy class is [`App\Security\FeaturePolicyBasic`](app/Security/FeaturePolicyBasic.php). Toggle and reporting options live in `config/feature-policy.php`. This is separate from CSP. Middleware is registered in [`bootstrap/app.php`](bootstrap/app.php).

## HTTP errors and locales

Custom Blade error pages live under [`resources/views/errors/`](resources/views/errors/) (shared partials under `errors/partials/`). Copy is driven by translation files such as [`lang/en_CH/errors.php`](lang/en_CH/errors.php) (and other `*_CH` locales). Feature coverage includes [`tests/Feature/HttpErrorPagesTest.php`](tests/Feature/HttpErrorPagesTest.php) (assertions use `APP_DEBUG=false`).

## Traits

We have added some traits which are located in the `app/Traits` directory.

- `HasUuidTrait`
- `HasActivityTrait`
- `HasNovaEnumLabelTrait`

We also have some traits which are located in the `app/Nova/Traits` directory which are intended for use only in Laravel
Nova.

- `NovaCustomOrderTrait`
- `NovaIdentificationPanelTrait`
- `NovaLanguageTrait`
- `NovaTimestampsPanelTrait`

## Blade and UI

Reusable UI primitives are [Blade components](https://laravel.com/docs/blade#components) under [`resources/views/components/ui/`](resources/views/components/ui/) (for example `x-ui.button`, `x-ui.input`, `x-ui.callout`).

Analytics and SEO partials are included from layouts, for example:

- [`resources/views/layouts/_partials/analytics/_fathom.blade.php`](resources/views/layouts/_partials/analytics/_fathom.blade.php)
- [`resources/views/layouts/_partials/analytics/_userback.blade.php`](resources/views/layouts/_partials/analytics/_userback.blade.php)
- [`resources/views/layouts/_partials/seo/_favicons.blade.php`](resources/views/layouts/_partials/seo/_favicons.blade.php)

Wire flash messages and SSO error UI via [`resources/views/layouts/_partials/_flash.blade.php`](resources/views/layouts/_partials/_flash.blade.php) ([Spatie Flash](https://github.com/spatie/laravel-flash) `flash()->message` / session keys).

## Cloudinary

Please refer to the respective documentation for the Cloudinary and Cloudinary Nova packages.

- [Flysystem Cloudinary](https://github.com/codebar-ag/laravel-flysystem-cloudinary).
- [Flysystem Cloudinary Nova](https://github.com/codebar-ag/laravel-flysystem-cloudinary-nova).

## Notifications

Transactional mail uses standard Laravel notifications under [`app/Notifications/`](app/Notifications/) (for example password reset and email verification). For in-page feedback, use [Spatie Laravel Flash](https://github.com/spatie/laravel-flash) together with [`_flash.blade.php`](resources/views/layouts/_partials/_flash.blade.php) and the `x-ui` components.

## Pint

We use Laravel Pint to format code.

You can run the following command to format your code:

```bash
./vendor/bin/pint
```

You can run the following command to format your blade files:

```bash
./vendor/bin/pint --blade
```

> The blade formatter is still in beta and may not work as expected. if you wish to not use this update your
> `composer.json` to use the latest version of `laravel/pint` instead of the dev branch.

## Production and performance

After deploy (and when not actively debugging config), cache framework metadata:

```bash
php artisan optimize
# or, when you need finer control:
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

For higher traffic, prefer **Redis** for `SESSION_DRIVER` and `CACHE_STORE` (see `config/database.php`, `config/session.php`, `config/cache.php`). Use `QUEUE_CONNECTION=database` or `redis` with a real worker process for anything slow (mail, imports, etc.); consider Laravel Horizon when you standardize on Redis queues.

Run **PHP with OPcache** enabled in production. For local builds use `npm run build`. For hosted bundles, run **`php artisan lasso:pull`** during deploy (before config/route/view cache) so `public/` matches the published bundle; see [Production assets (Lasso)](#production-assets-lasso).

### Database indexes

Review indexes on `users.email`, `microsoft_sso_identities`, activity log, and Spatie permission tables as your data grows.

### Microsoft OAuth tokens

`microsoft_sso_identities.token` and `refresh_token` are stored with Laravel’s **encrypted** cast (see the Entra SSO package). If you rotate `APP_KEY`, existing ciphertext cannot be decrypted—expect users to sign in with Microsoft again.

### GitHub Actions secrets

CI needs Composer authentication for **Nova** (`NOVA_USERNAME` / `NOVA_LICENSE_KEY` or `COMPOSER_AUTH`). Workflows in [`.github/workflows/`](.github/workflows/) also configure the **Flux** Composer registry (`FLUXUI_USERNAME` / `FLUXUI_LICENSE_KEY`) for consistency with Codebar pipelines; add any other secrets your jobs need (Nightwatch, OAuth test doubles, etc.). Do not commit real `.env` values; use repository secrets and short-lived tokens.

### Production assets (Lasso)

On every push to **`production`**, [`.github/workflows/lasso_publish_production.yml`](.github/workflows/lasso_publish_production.yml) runs `npm ci`, compiles with Vite inside **`php artisan lasso:publish`** (with `--no-git` and `--with-commit` from the Git commit SHA), uploads the bundle to object storage, then—if you set the secret below—**POSTs to your Laravel Cloud deploy hook** with `commit_hash` set to that SHA. A non-success HTTP response from the hook **fails the workflow** (unlike Lasso’s optional in-app webhook, which swallows HTTP errors). Configure **per-repository** secrets (Settings → Secrets and variables → Actions):

| Secret | Purpose |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | S3-compatible access key |
| `AWS_SECRET_ACCESS_KEY` | S3-compatible secret |
| `AWS_DEFAULT_REGION` | Region (e.g. `fra1` for DigitalOcean Spaces) |
| `AWS_BUCKET` | Bucket name |
| `AWS_ENDPOINT` | Optional; required for many S3-compatible providers (e.g. Spaces). Omit for default AWS endpoints. |
| `LARAVEL_CLOUD_DEPLOY_WEBHOOK_URL` | Optional; your Laravel Cloud **deploy hook** URL. The workflow calls it only **after** `lasso:publish` succeeds (see [Laravel Cloud deploy hooks](https://cloud.laravel.com/docs/deployments)). Omit the secret to skip deploy from this job. |

Use the same variables in your deployed app’s environment so **`lasso:pull`** reads from the same bucket/prefix. On Laravel Cloud, disable **deploy on every commit** if you rely on this hook so deploys start only after assets are uploaded.

[`config/lasso.php`](config/lasso.php) can still list `LARAVEL_CLOUD_DEPLOY_WEBHOOK_URL` under `webhooks.publish` for **local** `lasso:publish` runs with a real `.env`; **GitHub Actions does not** put that variable in the CI `.env`, so CI uses only the explicit workflow step and does not double-trigger the hook.

#### Laravel Cloud: environment and deploy script

On **Laravel Cloud**, set the same **S3-compatible** values as in production `.env` (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `AWS_ENDPOINT` if your provider needs it). Set **`LASSO_ENV`** to the same value used when GitHub runs `lasso:publish` (e.g. `production`). The `s3` disk uses a fixed bucket root (`/` in [`config/filesystems.php`](config/filesystems.php)); Lasso stores under `lasso/{LASSO_ENV}/` there.

In the environment’s **deploy script** (build/deploy commands Laravel Cloud runs for each deployment), pull compiled assets **after** dependencies and migrations are in place, and **before** you cache config/routes/views so `public/` already contains the bundle Vite emitted. For example:

```bash
php artisan migrate --force

php artisan lasso:pull --no-interaction

php artisan optimize
```

If you prefer explicit cache steps instead of `optimize`, run **`lasso:pull`** before `php artisan config:cache`, `route:cache`, `view:cache`, and `event:cache`. Do **not** rely on `npm run build` on Laravel Cloud if this template’s GitHub workflow already publishes bundles; otherwise you may overwrite or race the Lasso bundle.

If you set **`LARAVEL_CLOUD_DEPLOY_WEBHOOK_URL`** in Actions, turn off **automatic deploy on every Git push** in Laravel Cloud so each deploy runs only after this workflow finishes `lasso:publish` and successfully POSTs the deploy hook.

## Testing

We use [Pest](https://pestphp.com/) 4 on Laravel 13. PHPUnit/Pest use `DB_DATABASE=laravel_test` (see [`phpunit.xml`](phpunit.xml) and [`.env.testing`](.env.testing)); create that database on your Postgres instance before running tests (for example `createdb laravel_test`).

Match **pull-request CI** (PHPStan + Pest without `tests/Browser`):

```bash
composer verify
```

That runs `composer analyse` (Larastan / PHPStan) and `composer test` (same paths as [`.github/workflows/pest_pull_request.yml`](.github/workflows/pest_pull_request.yml)).

Pest **type coverage** (also used in CI; does not require Xdebug):

```bash
composer test:type-coverage
```

Pest **code coverage** with the `--min=90` gate needs **PCOV** or **Xdebug** enabled for PHP. Without a driver, Pest exits with “No code coverage driver is available.” CI enables Xdebug for that job. Locally:

```bash
composer test:coverage
```

Run the full default suite (includes `tests/Browser`; those tests skip unless `RUN_BROWSER_TESTS=1` / Dusk is set up):

```bash
composer test:all
# or: php artisan test --compact
```

Run a subset with a path or filter:

```bash
php artisan test --compact tests/Feature/HttpErrorPagesTest.php
php artisan test --compact --filter=someTestName
```

You can also invoke Pest directly:

```bash
./vendor/bin/pest
```

**Browser tests (Dusk):** copy [`.env.dusk.example`](.env.dusk.example) to `.env.dusk.local` and align `APP_URL` with your local HTTPS domain (see the comment in [`.env.example`](.env.example)). Then:

```bash
composer dusk
# or: php artisan dusk
```

Static analysis uses Larastan / PHPStan (`./vendor/bin/phpstan analyse` or `composer analyse`). The `type_coverage` thresholds in [`phpstan.neon.dist`](phpstan.neon.dist) are set to a low baseline (`10` on param/return/property/constant, `0` on `declare`) so CI stays green; raise them as you add types.

Please refer to the [PestPHP](https://pestphp.com) documentation for more information on how to use PestPHP.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
