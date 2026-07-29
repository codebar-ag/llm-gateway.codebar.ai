<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\BacklogEntry;
use App\Support\Duration;
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
                'backlog' => route('backlog'),
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

        return [
            'id' => $entry->id,
            'status' => [
                'label' => $entry->status->label(),
                'tone' => $entry->status->tone(),
            ],
            'facts' => [
                ['label' => 'Model', 'value' => $entry->model ?? '—'],
                ['label' => 'Gateway', 'value' => $gateway === null ? '—' : $gateway->name],
                ['label' => 'Upstream', 'value' => $gateway === null ? '—' : $gateway->endpoint()],
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
            'answer' => data_get($entry->output, '0.content.0.text'),
            'protocolNote' => $gateway !== null && ! $gateway->protocol->isPassthrough()
                ? 'Sent as '.$gateway->protocol->label().': the driver rewrites this body on the way out.'
                : null,
            'request' => $entry->request,
            'usage' => $entry->usage,
            'headerNames' => $entry->headerNames(),
            'output' => $entry->output,
            'error' => $entry->error === null ? null : [
                'details' => $entry->errorDetails(),
                'raw' => $entry->error,
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
            'request' => route('backlog.entry.download', ['id' => $entry->id, 'part' => 'request']),
            'response' => route('backlog.entry.download', ['id' => $entry->id, 'part' => 'response']),
            'answer' => route('backlog.entry.download', ['id' => $entry->id, 'part' => 'answer']),
            'error' => route('backlog.entry.download', ['id' => $entry->id, 'part' => 'error']),
        ];
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
