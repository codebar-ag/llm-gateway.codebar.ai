<?php

declare(strict_types=1);

use App\Models\ApiKeyLabel;
use App\Models\BacklogEntry;

it('lands on the dashboard after signing in', function () {
    BacklogEntry::factory()->count(2)->completed()->create(['model' => 'chat-model']);

    [$page] = signIn();

    $page->assertPathIs('/')
        ->assertSee('LLM Gateway')
        ->assertSee('Usage')
        ->assertSee('Last 7 days')
        ->assertSee('Per model')
        ->assertSee('Per caller')
        ->assertSee('chat-model');
});

it('sends a status tile into the logs already filtered', function () {
    BacklogEntry::factory()->failed()->create(['id' => 'resp_failedtile']);
    BacklogEntry::factory()->completed()->create(['id' => 'resp_completedtile']);

    [$page] = signIn();

    // 4th tile = failed, in BacklogStatus declaration order.
    $page->click('.grid-cols-2 > a:nth-child(4)')
        ->assertPathIs('/logs')
        ->assertQueryStringHas('status', 'failed')
        ->assertSee('#resp_failedtile')
        ->assertDontSee('#resp_completedtile');
});

it('switches the window from the range picker and keeps it in the URL', function () {
    BacklogEntry::factory()->completed()->create();

    [$page] = signIn();

    $page->click('a:text-is("24h")')
        ->assertQueryStringHas('range', '24h')
        ->assertSee('Last 24 hours');
});

it('names a labelled caller in the per-caller breakdown', function () {
    ApiKeyLabel::factory()->forKey('sk-nightly')->create(['name' => 'Nightly batch']);

    BacklogEntry::factory()->completed()->create([
        'api_key_hash' => hash('sha256', 'sk-nightly'),
    ]);

    [$page] = signIn();

    $page->assertSee('Nightly batch');
});

it('says so rather than drawing an empty chart when nothing was queued', function () {
    [$page] = signIn();

    $page->assertSee('No traffic');
});
