<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ListCallersController
{
    public function __invoke(): Response
    {
        /** `pluck()` widens to `mixed`; `rows()` needs the narrow type. */
        /** @var Collection<string, string> $labels */
        $labels = ApiKeyLabel::query()->pluck('name', 'api_key_hash');

        return Inertia::render('Callers', [
            'callers' => self::rows($labels),
            'urls' => [
                'store' => route('callers.store'),
            ],
        ]);
    }

    /**
     * Every caller the backlog has seen, most recently active first.
     *
     * Driven by the entries rather than by the labels: a name nobody has used
     * is not a caller, and a caller nobody has named still has to be listed —
     * that is the whole point of the page.
     *
     * @param  Collection<string, string>  $labels
     * @return array<int, array<string, mixed>>
     */
    private static function rows(Collection $labels): array
    {
        return BacklogEntry::callerActivity()
            ->sortByDesc(fn (array $caller): string => $caller['last_seen']->toDateTimeString())
            ->map(function (array $caller) use ($labels): array {
                $hash = $caller['api_key_hash'] ?? ApiKeyLabel::ANONYMOUS;

                return [
                    'hash' => $hash,
                    'shortHash' => $hash === ApiKeyLabel::ANONYMOUS ? null : substr($hash, 0, 12),
                    'name' => $labels->get($hash) ?? '',
                    'isAnonymous' => $caller['api_key_hash'] === null,
                    'entries' => $caller['entries'],
                    'firstSeen' => [
                        'exact' => $caller['first_seen']->toDateTimeString(),
                        'human' => $caller['first_seen']->diffForHumans(),
                    ],
                    'lastSeen' => [
                        'exact' => $caller['last_seen']->toDateTimeString(),
                        'human' => $caller['last_seen']->diffForHumans(),
                    ],
                    'entriesUrl' => route('logs', ['caller' => $hash]),
                    'destroyUrl' => route('callers.destroy', ['hash' => $hash]),
                ];
            })
            ->values()
            ->all();
    }
}
