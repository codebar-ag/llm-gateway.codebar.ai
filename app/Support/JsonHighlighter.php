<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

class JsonHighlighter
{
    private const ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** What a JSON string literal looks like once its quotes are HTML entities. */
    private const QUOTE = '&quot;';

    /**
     * Strings are matched first so numbers and keywords inside them are not
     * mistaken for values of their own.
     *
     * Matching runs on already-escaped text, so a string literal is delimited by
     * `&quot;` rather than `"`. Its body is "an escape sequence, or any character
     * that does not begin the closing entity".
     */
    private const TOKENS = '/('.self::QUOTE.'(?:\\\\.|(?!'.self::QUOTE.').)*'.self::QUOTE.')(\s*:)?'
        .'|\b(true|false|null)\b'
        .'|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/';

    public static function render(mixed $value): HtmlString
    {
        $json = json_encode($value, self::ENCODE_FLAGS);

        if ($json === false) {
            return new HtmlString('');
        }

        /*
         | ENT_QUOTES, not ENT_NOQUOTES. Every use of this today renders into a
         | <pre> text node, where an unescaped `"` is harmless — but leaving
         | quotes intact makes that placement load-bearing, and the first time
         | the result lands in an HTML attribute a payload could close it. The
         | token pattern reads `&quot;` instead so nothing is given up for it.
         */
        $escaped = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new HtmlString((string) preg_replace_callback(self::TOKENS, self::token(...), $escaped));
    }

    /** @param  array<int|string, string>  $match */
    private static function token(array $match): string
    {
        if (($match[2] ?? '') !== '') {
            return self::wrap('key', $match[1]).$match[2];
        }

        if (($match[1] ?? '') !== '') {
            return self::wrap('string', $match[1]);
        }

        if (($match[3] ?? '') !== '') {
            return self::wrap('const', $match[3]);
        }

        return self::wrap('number', $match[0]);
    }

    private static function wrap(string $token, string $text): string
    {
        return '<span class="json-'.$token.'">'.$text.'</span>';
    }
}
