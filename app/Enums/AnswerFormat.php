<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an entry's answer should be treated once it leaves the model.
 *
 * The answer is whatever the model wrote. A model asked for structured output
 * writes JSON, and calling that markdown mislabels both the download and the
 * syntax highlighting — so the format is read off the content rather than
 * assumed from the field it arrived in.
 */
enum AnswerFormat: string
{
    case Json = 'json';

    case Markdown = 'markdown';

    /**
     * A bare scalar such as `42` is valid JSON on its own, but a model that
     * answers with a number wrote prose rather than a document. Only an object
     * or an array counts, which is what structured output means in practice.
     */
    public static function of(?string $answer): self
    {
        $trimmed = ltrim($answer ?? '');

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return self::Markdown;
        }

        return json_validate($trimmed) ? self::Json : self::Markdown;
    }

    public function extension(): string
    {
        return match ($this) {
            self::Json => 'json',
            self::Markdown => 'md',
        };
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Json => 'application/json',
            self::Markdown => 'text/markdown; charset=utf-8',
        };
    }
}
