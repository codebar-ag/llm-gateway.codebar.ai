<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The caller's headers, kept as they arrived so the worker can replay the
 * request unchanged — but only the ones the upstream has any business seeing.
 *
 * This is an allowlist on purpose. A denylist has to be right about every
 * header that exists now and every header a client invents later, and it only
 * takes one to send a browser's `Cookie` to LiteLLM. The prefixes keep custom
 * routing and tagging headers working without opening that door.
 */
class ForwardedHeaders
{
    private const FORWARDED = [
        'authorization', 'content-type', 'accept', 'accept-language',
        'user-agent', 'x-request-id',
    ];

    private const FORWARDED_PREFIXES = ['x-litellm-', 'openai-', 'anthropic-'];

    /** @return array<string, string> */
    public static function from(Request $request): array
    {
        return self::names($request)
            ->mapWithKeys(fn (array $values, string $name) => [$name => implode(', ', $values)])
            ->all();
    }

    public static function keyHash(Request $request): ?string
    {
        $token = (string) $request->bearerToken();

        if ($token === '') {
            return null;
        }

        return hash('sha256', $token);
    }

    /** @return Collection<string, list<string|null>> */
    private static function names(Request $request): Collection
    {
        return collect($request->headers->all())
            ->filter(fn (array $values, string $name) => self::isForwarded(strtolower($name)));
    }

    private static function isForwarded(string $name): bool
    {
        return in_array($name, self::FORWARDED, true)
            || Str::startsWith($name, self::FORWARDED_PREFIXES);
    }
}
