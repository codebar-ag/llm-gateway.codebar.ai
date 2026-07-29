# Contributing

Thanks for taking the time to contribute. This project is intentionally small
in scope — see the [README](README.md) for what it does and, just as
importantly, what it deliberately doesn't do — so the bar for new code is
that it fits that scope, not just that it works.

## Reporting bugs and requesting features

Search [existing issues](https://github.com/codebar-ag/llm-gateway.codebar.ai/issues)
first. For a bug, include:

- what you expected vs. what happened
- steps to reproduce, including the request body if relevant (redact any real
  bearer tokens)
- relevant log lines from `storage/logs/laravel-*.log`

For a **security vulnerability**, do not open a public issue — see
[SECURITY.md](SECURITY.md) instead.

## Development setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
createdb llm_gateway
php artisan migrate
```

Run the app and a worker locally as described in the README's
[Installation](README.md#installation) section.

## Before opening a pull request

```bash
composer verify   # Pint, PHPStan level 10, Pest — same checks CI runs
```

or individually:

```bash
composer lint     # Pint — code style
composer analyse  # PHPStan level 10
composer test     # Pest
```

A few things worth knowing before you touch the code:

- **Strict types everywhere.** Every PHP file starts with
  `declare(strict_types=1);`.
- **PHPStan runs at level 10.** Prefer precise types over `mixed`, and use the
  `Config::string()` / `Config::integer()` accessors already used in
  `config/gateway.php` rather than raw `env()` calls in application code.
- **New behaviour needs a Pest test.** `tests/Feature` covers the HTTP
  surface, `tests/Unit` covers services and support classes.
- **Passthrough is the point.** Changes that make the gateway inspect,
  validate or rewrite the request/response body need a strong justification —
  see the README's [Passthrough](README.md#passthrough) section for why that's
  deliberate, not an oversight.

## Pull requests

- Keep PRs focused; unrelated cleanups make review slower, not faster.
- Explain the *why* in the description, not just the *what* — the diff already
  shows what changed.
- CI (Pint, Larastan, Pest) must pass before merge.

## Code of conduct

Be respectful and constructive. Disagreements about code are fine; treat
people well regardless.
