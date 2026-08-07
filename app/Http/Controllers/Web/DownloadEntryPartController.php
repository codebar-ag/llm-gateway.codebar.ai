<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\AnswerFormat;
use App\Models\BacklogEntry;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadEntryPartController
{
    /**
     * The parts of an entry that can be taken away as a file.
     *
     * The route carries the same list as a constraint. The duplication is
     * deliberate: a constraint is easy to widen, and this is what still
     * decides the question after someone has widened it.
     *
     * @var list<string>
     */
    private const PARTS = ['request', 'response', 'answer', 'error'];

    /** Bigger than this, a payload is streamed as stored rather than re-encoded. */
    private const PRETTY_LIMIT = 1_000_000;

    public function __invoke(string $id, string $part): StreamedResponse
    {
        abort_unless(in_array($part, self::PARTS, true), 404);

        $entry = BacklogEntry::query()->findOrFail($id);

        $content = self::content($entry, $part);

        // A queued entry has no answer and a completed one has no error. Either
        // would otherwise download as a file with nothing in it, which reads as
        // "the answer was empty" rather than "there is no answer yet".
        abort_if($content === null, 404);

        // Every part but the answer is this application's own JSON. The answer
        // is the model's, so it gets whichever format it actually turned out to
        // be. The bytes go out untouched either way: re-encoding a JSON answer
        // to pretty-print it would quietly rewrite `1284.50` as `1284.5`.
        $format = $part === 'answer' ? AnswerFormat::of($content) : AnswerFormat::Json;

        return Response::streamDownload(
            function () use ($content): void {
                echo $content;
            },
            self::filename($entry->id, $part, $format),
            ['Content-Type' => $format->contentType()],
            'attachment',
        );
    }

    private static function content(BacklogEntry $entry, string $part): ?string
    {
        return match ($part) {
            'request' => self::json($entry, 'request'),
            'response' => self::json($entry, 'output'),
            'answer' => self::text($entry->answerText()),
            'error' => self::json($entry, 'error'),
            default => null,
        };
    }

    /**
     * The id already carries its `resp_` prefix, so it needs no decoration —
     * but it reaches a filename, so it goes through basename() first, whatever
     * shape the route constrains it to today.
     */
    private static function filename(string $id, string $part, AnswerFormat $format): string
    {
        return basename($id).'_'.$part.'.'.$format->extension();
    }

    /**
     * The stored JSON, pretty-printed while that is affordable.
     *
     * Pretty-printing means decoding the column and encoding it again, and an
     * OCR request carries its image through both copies — past PHP-FPM's
     * memory limit, the download is a blank 500. Past the threshold the file
     * is the stored bytes untouched, which no ceiling can be blown by and
     * which any editor pretty-prints on its own.
     *
     * @param  literal-string  $column
     */
    private static function json(BacklogEntry $entry, string $column): ?string
    {
        $stored = $entry->getRawOriginal($column);

        if (! is_string($stored)) {
            return null;
        }

        if (strlen($stored) > self::PRETTY_LIMIT) {
            return $stored;
        }

        return json_encode($entry->getAttribute($column), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
