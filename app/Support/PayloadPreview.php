<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * A stored payload, cut down to what a page can afford to carry.
 *
 * An OCR call brings its image into `request` as one base64 string, and the
 * entry page used to inline it whole: decoded out of Postgres, re-encoded into
 * the Inertia props, and escaped into the HTML the page boots from — three
 * copies of something no one can read, and past PHP-FPM's memory limit the
 * page is a blank 500 wearing Inertia's empty error modal.
 *
 * Embeddings do the same thing with numbers instead of text: one entry's
 * output is thousands of floats per vector, which decode into PHP arrays at
 * many times their JSON size and render as megabytes nobody scrolls. So long
 * lists are capped the way long strings are cut.
 *
 * Only the leaves and the list lengths change, so the payload keeps its
 * shape — the model, the roles, every key an operator actually compares
 * against an upstream's logs — and stays valid JSON for the panes that
 * pretty-print it. Each cut names what it removed and points at the download,
 * which is the part built to hand over the whole value.
 */
final class PayloadPreview
{
    /** Longer than this, a string is a blob being carried, not text being read. */
    private const LIMIT = 10_000;

    /** How much of a shortened string the page still shows. */
    private const KEEP = 1_000;

    /** Longer than this, a list is a vector being carried, not fields being read. */
    private const LIST_LIMIT = 100;

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @return array<array-key, mixed>|null
     */
    public static function of(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        /** @var array<array-key, mixed> */
        return self::value($payload);
    }

    private static function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return strlen($value) > self::LIMIT ? self::shortened($value) : $value;
        }

        if (is_array($value)) {
            return array_map(self::value(...), self::capped($value));
        }

        return $value;
    }

    /**
     * A list cut to its head, with the cut stated where the tail was.
     *
     * Only lists: an object's keys are the shape being previewed, however many
     * there are, while a thousand-element list is a vector or a batch — cargo
     * the download exists for.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function capped(array $value): array
    {
        if (count($value) <= self::LIST_LIMIT || ! array_is_list($value)) {
            return $value;
        }

        $removed = number_format(count($value) - self::LIST_LIMIT);

        return [
            ...array_slice($value, 0, self::LIST_LIMIT),
            "… [{$removed} more values — the download holds the whole list]",
        ];
    }

    private static function shortened(string $value): string
    {
        $removed = Number::fileSize(strlen($value) - self::KEEP);

        return Str::limit($value, self::KEEP, "… [{$removed} removed — the download holds the whole value]");
    }
}
