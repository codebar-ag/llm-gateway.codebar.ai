<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Loads third-party CSP source strings from {@see config/csp-allowlists.json}.
 *
 * @phpstan-type DirectiveKey 'connect'|'img'|'media'|'font'|'script'|'style_elem'|'style'
 */
final class CspAllowlist
{
    /**
     * @param  DirectiveKey  $directive
     * @return list<string>
     */
    public static function sources(string $directive): array
    {
        $data = self::load();

        /** @var list<string> */
        return Arr::get($data, $directive, []);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function load(): array
    {
        $path = config_path('csp-allowlists.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
