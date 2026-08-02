<?php

declare(strict_types=1);

use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/*
 | Captures the dashboard screenshots used in marketing material. Everything
 | here is synthetic — no real prompt ever reaches a published image. Run with
 | `php artisan test --filter=MarketingScreenshots`; the files land in
 | tests/Browser/Screenshots.
 */

it('captures the backlog and entry detail screens', function () {
    config([
        'gateway.tunnel.metrics_url' => 'http://127.0.0.1:20241',
        'gateway.backlog.workers' => 2,
    ]);

    Http::fake(['127.0.0.1:20241/*' => Http::response('OK', 200)]);

    // Reuse the migration-seeded default rather than adding a second one, so
    // the header line shows a plausible endpoint instead of upstream.invalid.
    $ollama = Gateway::default() ?? Gateway::factory()->isDefault()->create();
    $ollama->update([
        'name' => 'MacBook Pro · Ollama',
        'base_url' => 'http://10.0.1.14:11434',
    ]);

    $liteLlm = Gateway::factory()->create([
        'name' => 'LiteLLM',
        'base_url' => 'http://10.0.1.14:4000',
    ]);

    foreach (['Rechnungsimport', 'Protokoll-Assistent', 'Dokumentklassierung'] as $index => $label) {
        ApiKeyLabel::factory()->create([
            'api_key_hash' => hash('sha256', 'sk-demo-caller-'.$index),
            'name' => $label,
        ]);
    }

    $caller = fn (int $index): array => [
        'api_key_hash' => hash('sha256', 'sk-demo-caller-'.$index),
    ];

    // A running batch, a queue behind it, and a settled history above.
    BacklogEntry::factory()->running()->create([
        ...$caller(0),
        'gateway_id' => $ollama->id,
        'model' => 'qwen3-vl:30b-a3b',
        'started_at' => CarbonImmutable::now()->subSeconds(38),
    ]);

    BacklogEntry::factory()->running()->create([
        ...$caller(2),
        'gateway_id' => $ollama->id,
        'model' => 'qwen3-vl:30b-a3b',
        'started_at' => CarbonImmutable::now()->subSeconds(12),
    ]);

    foreach (range(1, 6) as $offset) {
        BacklogEntry::factory()->create([
            ...$caller($offset % 3),
            'gateway_id' => $offset % 2 === 0 ? $ollama->id : $liteLlm->id,
            'model' => $offset % 2 === 0 ? 'qwen3-vl:30b-a3b' : 'llama3.2:3b',
            'created_at' => CarbonImmutable::now()->subSeconds(90 - ($offset * 9)),
        ]);
    }

    foreach (range(1, 9) as $offset) {
        $createdAt = CarbonImmutable::now()->subMinutes(4 + $offset);
        $waited = 6 + ($offset * 4);
        $ran = 9 + (($offset * 7) % 23);

        BacklogEntry::factory()->completed('{"rechnungsnummer":"2026-4471","total":1284.50}')->create([
            ...$caller($offset % 3),
            'gateway_id' => $offset % 3 === 0 ? $liteLlm->id : $ollama->id,
            'model' => $offset % 3 === 0 ? 'llama3.2:3b' : 'qwen3-vl:30b-a3b',
            'created_at' => $createdAt,
            'started_at' => $createdAt->addSeconds($waited),
            'finished_at' => $createdAt->addSeconds($waited + $ran),
        ]);
    }

    $failedAt = CarbonImmutable::now()->subMinutes(21);
    BacklogEntry::factory()->failed('ConnectionException: connection timed out after 900s')->create([
        ...$caller(1),
        'gateway_id' => $liteLlm->id,
        'model' => 'llama3.2:3b',
        'created_at' => $failedAt,
        'started_at' => $failedAt->addSeconds(14),
        'finished_at' => $failedAt->addSeconds(31),
    ]);

    // The one entry we open in detail — a complete, readable extraction job.
    $featured = BacklogEntry::factory()
        ->completed('{"rechnungsnummer":"2026-4471","datum":"2026-07-14","total":1284.50,"waehrung":"CHF"}')
        ->create([
            ...$caller(0),
            'gateway_id' => $ollama->id,
            'model' => 'qwen3-vl:30b-a3b',
            'request' => [
                'model' => 'qwen3-vl:30b-a3b',
                'messages' => [[
                    'role' => 'user',
                    'content' => 'Lies Rechnungsnummer, Datum, Total und Währung aus dem Beleg und '
                        .'gib sie als JSON zurück.',
                ]],
            ],
            'usage' => ['input_tokens' => 1174, 'output_tokens' => 38, 'total_tokens' => 1212],
            'created_at' => CarbonImmutable::now()->subMinutes(2),
            'started_at' => CarbonImmutable::now()->subMinutes(2)->addSeconds(46),
            'finished_at' => CarbonImmutable::now()->subMinutes(2)->addSeconds(63),
        ]);

    [$page] = signIn(User::factory()->create([
        'name' => 'Sebastian Bürgin',
        'email' => 'sebastian.buergin@codebar.ch',
    ]));

    $page->resize(1440, 1000)
        ->screenshot(filename: 'llm-gateway-backlog');

    // Shorter viewport: the detail page ends well above 1000px and a full-page
    // capture would otherwise pad the image with empty canvas.
    visit("/entries/{$featured->id}")
        ->resize(1440, 640)
        ->screenshot(filename: 'llm-gateway-entry');
})->group('marketing');
