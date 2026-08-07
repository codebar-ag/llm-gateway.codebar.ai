<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\AnswerFormat;
use App\Enums\BacklogKind;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Support\Duration;
use App\Support\PayloadPreview;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ShowBacklogEntryController
{
    public function __invoke(string $id): Response
    {
        // The page names the gateway four times; without this it is a second
        // query rather than a violation, since strict mode only rejects a lazy
        // load once more than one model was hydrated.
        $entry = BacklogEntry::query()->with('gateway')->findOrFail($id);

        return Inertia::render('Entry', [
            'entry' => self::payload($entry),
            'urls' => [
                'logs' => route('logs'),
                'downloads' => self::downloads($entry),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private static function payload(BacklogEntry $entry): array
    {
        // Explicit, because `gateway_id` is nullOnDelete and the relation really
        // can be null — whatever the static analyser infers from the belongsTo.
        $gateway = $entry->gateway;
        // A chat answer is the assistant's text; an embedding or a moderation
        // is summarised, with the body itself one click away under Response.
        $answer = $entry->answerText();

        return [
            'id' => $entry->id,
            'status' => [
                'label' => $entry->status->label(),
                'tone' => $entry->status->tone(),
            ],
            'facts' => [
                ['label' => 'Endpoint', 'value' => $entry->kind->label()],
                ['label' => 'Model', 'value' => $entry->model ?? '—', 'exact' => $entry->model],
                ['label' => 'Gateway', 'value' => $gateway === null ? '—' : $gateway->name],
                // Long enough to be truncated in its tile, so it carries the
                // whole URL as the tooltip the page reads `exact` for.
                ['label' => 'Upstream', 'value' => $gateway === null ? '—' : $gateway->endpoint(), 'exact' => $gateway?->endpoint()],
                ['label' => 'Attempts', 'value' => $entry->attempts.' / '.$entry->max_attempts],
                ['label' => 'Total tokens', 'value' => $entry->usage['total_tokens'] ?? '—'],
                ['label' => 'Waited', 'value' => Duration::human($entry->waitedSeconds()), 'exact' => Duration::exact($entry->waitedSeconds())],
                ['label' => 'Duration', 'value' => Duration::human($entry->durationSeconds()), 'exact' => Duration::exact($entry->durationSeconds())],
                ['label' => 'Forwarded headers', 'value' => count($entry->headerNames()) ?: '— (cleared)'],
            ],
            'times' => [
                self::moment('Created', $entry->created_at),
                self::moment('Started', $entry->started_at),
                self::moment('Finished', $entry->finished_at),
                self::moment('Updated', $entry->updated_at),
            ],
            'answer' => $answer,
            'answerFormat' => AnswerFormat::of(is_string($answer) ? $answer : null)->value,
            'answerNote' => self::answerNote($entry),
            'protocolNote' => self::protocolNote($gateway),
            /*
             | Previewed, not whole: an OCR request carries its image as one
             | base64 string, and inlining it put the page past PHP-FPM's
             | memory limit — a blank 500 in Inertia's error modal. The
             | download routes below are where the whole value lives.
             */
            'request' => PayloadPreview::of($entry->request),
            'usage' => $entry->usage,
            'headerNames' => $entry->headerNames(),
            'output' => PayloadPreview::of($entry->output),
            'error' => $entry->error === null ? null : [
                'details' => $entry->errorDetails(),
                'raw' => PayloadPreview::of($entry->error),
            ],
            'apiKeyHash' => $entry->api_key_hash,
        ];
    }

    /**
     * Where each part of the entry can be downloaded from.
     *
     * Every part is offered here and the page hides the ones it has no pane
     * for; the route itself 404s a part with nothing in it, so a link that
     * slipped through cannot hand anyone an empty file.
     *
     * @return array<string, string>
     */
    private static function downloads(BacklogEntry $entry): array
    {
        return [
            'request' => route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']),
            'response' => route('logs.entry.download', ['id' => $entry->id, 'part' => 'response']),
            'answer' => route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']),
            'error' => route('logs.entry.download', ['id' => $entry->id, 'part' => 'error']),
        ];
    }

    /**
     * Warn that the answer shown is not the answer the caller received.
     *
     * An embedding entry's answer pane shows the shape of the vectors rather
     * than thousands of floats. Without saying so, the pane reads as an
     * upstream that answered with one line of prose.
     */
    private static function answerNote(BacklogEntry $entry): ?string
    {
        return $entry->kind === BacklogKind::Embeddings
            ? 'Summarised. The vectors themselves are stored whole for the caller to collect — read them under Response.'
            : null;
    }

    /**
     * Warn that the request shown is not the request that went on the wire.
     *
     * The page renders what was stored, which is what the caller sent. A
     * translating gateway rewrites it on the way out, so without this an
     * operator comparing the pane against their upstream's logs would be
     * reading two different bodies and looking for the bug in the wrong one.
     *
     * Nothing is said for a passthrough gateway, or for one force deleted out
     * from under its entries — in both cases there is no translation to warn
     * about, which is a different thing from an empty warning.
     */
    private static function protocolNote(?Gateway $gateway): ?string
    {
        if ($gateway === null || $gateway->protocol->isPassthrough()) {
            return null;
        }

        return 'Sent as '.$gateway->protocol->label().': the driver rewrites this body on the way out.';
    }

    /** @return array{label: string, exact: string|null, human: string} */
    private static function moment(string $label, ?CarbonImmutable $moment): array
    {
        return [
            'label' => $label,
            'exact' => $moment?->toDateTimeString(),
            'human' => $moment?->diffForHumans() ?? 'not reached yet',
        ];
    }
}
