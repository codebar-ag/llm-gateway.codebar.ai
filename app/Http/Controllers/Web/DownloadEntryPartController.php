<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

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

    public function __invoke(string $id, string $part): StreamedResponse
    {
        abort_unless(in_array($part, self::PARTS, true), 404);

        $entry = BacklogEntry::query()->findOrFail($id);

        $content = self::content($entry, $part);

        // A queued entry has no answer and a completed one has no error. Either
        // would otherwise download as a file with nothing in it, which reads as
        // "the answer was empty" rather than "there is no answer yet".
        abort_if($content === null, 404);

        return Response::streamDownload(
            function () use ($content): void {
                echo $content;
            },
            self::filename($entry->id, $part),
            ['Content-Type' => self::contentType($part)],
            'attachment',
        );
    }

    private static function content(BacklogEntry $entry, string $part): ?string
    {
        return match ($part) {
            'request' => self::json($entry->request),
            'response' => self::json($entry->output),
            'answer' => self::text(data_get($entry->output, '0.content.0.text')),
            'error' => self::json($entry->error),
            default => null,
        };
    }

    /**
     * The id already carries its `resp_` prefix, so it needs no decoration —
     * but it reaches a filename, so it goes through basename() first, whatever
     * shape the route constrains it to today.
     */
    private static function filename(string $id, string $part): string
    {
        $extension = $part === 'answer' ? 'md' : 'json';

        return basename($id).'_'.$part.'.'.$extension;
    }

    private static function contentType(string $part): string
    {
        return $part === 'answer' ? 'text/markdown; charset=utf-8' : 'application/json';
    }

    /** @param  array<array-key, mixed>|null  $value */
    private static function json(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
