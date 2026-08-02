<?php

declare(strict_types=1);

use App\Models\BacklogEntry;
use App\Models\Gateway;

/**
 * Selector for a tab button by its visible label, scoped to the ARIA
 * tablist so it never collides with the same word appearing inside a code
 * preview or a fact card — `click('Response')` alone is ambiguous the
 * moment a JSON body happens to contain that word.
 */
function entryTab(string $label): string
{
    return sprintf('[role="tab"]:has-text("%s")', $label);
}

it('shows a queued entry with only the Request, Response, and Meta tabs, defaulting to Request', function () {
    $entry = BacklogEntry::factory()->create();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->assertSee($entry->id)
        ->assertSee('queued')
        ->assertCount('[role="tab"]', 3)
        ->assertPresent(entryTab('Request'))
        ->assertPresent(entryTab('Response'))
        ->assertPresent(entryTab('Meta'))
        ->assertNotPresent(entryTab('Error'))
        ->assertNotPresent(entryTab('Answer'))
        ->assertAttribute(entryTab('Request'), 'aria-selected', 'true')
        ->assertAttribute(entryTab('Response'), 'aria-selected', 'false')
        ->assertAttribute(entryTab('Meta'), 'aria-selected', 'false')
        // Only Request and Response have a download link on a queued entry.
        // An anchor, not a button: these are real `href`s so the browser does
        // the download, which is what the anchor-downloads test below pins.
        ->assertCount('a:has-text("Download")', 2);
});

it('shows a completed entry with an additional Answer tab, defaulting to Answer', function () {
    $entry = BacklogEntry::factory()->completed('The answer is 42.')->create();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->assertCount('[role="tab"]', 4)
        ->assertPresent(entryTab('Answer'))
        ->assertPresent(entryTab('Request'))
        ->assertPresent(entryTab('Response'))
        ->assertPresent(entryTab('Meta'))
        ->assertNotPresent(entryTab('Error'))
        ->assertAttribute(entryTab('Answer'), 'aria-selected', 'true')
        ->assertAttribute(entryTab('Request'), 'aria-selected', 'false')
        ->wait(0.5)
        ->assertSee('The answer is 42.');
});

it('shows a failed entry with an additional Error tab, defaulting to Error, and no Answer tab', function () {
    $entry = BacklogEntry::factory()->failed('Upstream exploded')->create();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->assertSee('failed')
        ->assertCount('[role="tab"]', 4)
        ->assertPresent(entryTab('Error'))
        ->assertPresent(entryTab('Request'))
        ->assertPresent(entryTab('Response'))
        ->assertPresent(entryTab('Meta'))
        ->assertNotPresent(entryTab('Answer'))
        ->assertAttribute(entryTab('Error'), 'aria-selected', 'true')
        ->wait(0.5)
        ->assertSee('Upstream exploded');
});

it('switches tabs on click, updating aria-selected and swapping the visible panel content', function () {
    $entry = BacklogEntry::factory()->completed('ANSWER_MARKER_TEXT')->create([
        'request' => [
            'model' => 'marker-model',
            'messages' => [['role' => 'user', 'content' => 'REQUEST_MARKER_TEXT']],
        ],
    ]);

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->wait(0.5)
        ->assertAttribute(entryTab('Answer'), 'aria-selected', 'true')
        ->assertSee('ANSWER_MARKER_TEXT')
        ->assertDontSee('REQUEST_MARKER_TEXT')
        ->click(entryTab('Request'))
        ->assertAttribute(entryTab('Request'), 'aria-selected', 'true')
        ->assertAttribute(entryTab('Answer'), 'aria-selected', 'false')
        ->assertSee('REQUEST_MARKER_TEXT')
        ->assertDontSee('ANSWER_MARKER_TEXT');
});

it('navigates tabs with ArrowRight and ArrowLeft, wrapping at each end', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    [$page] = signIn();

    // Tab order for a completed entry (no error): Answer, Request, Response, Meta.
    $page->navigate("/entries/{$entry->id}")
        ->assertAttribute(entryTab('Answer'), 'aria-selected', 'true')
        ->keys(entryTab('Answer'), 'ArrowRight')
        ->assertAttribute(entryTab('Request'), 'aria-selected', 'true')
        ->assertAttribute(entryTab('Answer'), 'aria-selected', 'false')
        ->keys(entryTab('Request'), 'ArrowRight')
        ->assertAttribute(entryTab('Response'), 'aria-selected', 'true')
        // Wrapping left from the first tab lands on the last tab.
        ->keys(entryTab('Answer'), 'ArrowLeft')
        ->assertAttribute(entryTab('Meta'), 'aria-selected', 'true');
});

it('shows the api key hash copy button when present, hides it when absent, and never shows header values', function () {
    $withKey = BacklogEntry::factory()->completed()->create();
    $anonymous = BacklogEntry::factory()->completed()->create(['api_key_hash' => null]);

    [$page] = signIn();

    $page->navigate("/entries/{$withKey->id}")
        ->click(entryTab('Meta'))
        ->assertAttribute(entryTab('Meta'), 'aria-selected', 'true')
        ->assertSee('Values are never shown')
        ->assertPresent('[id$="-panel-meta"] button[aria-label="Copy to clipboard"]');

    expect(trim((string) $page->text('[id$="-panel-meta"] code')))->toBe($withKey->api_key_hash);

    $page->navigate("/entries/{$anonymous->id}")
        ->click(entryTab('Meta'))
        ->assertNotPresent('[id$="-panel-meta"] button[aria-label="Copy to clipboard"]');

    expect(trim((string) $page->text('[id$="-panel-meta"] code')))->toBe('—');
});

it('offers real anchor downloads for every present part, and omits the button for tabs that do not exist', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $requestUrl = route('backlog.entry.download', ['id' => $entry->id, 'part' => 'request']);
    $responseUrl = route('backlog.entry.download', ['id' => $entry->id, 'part' => 'response']);
    $answerUrl = route('backlog.entry.download', ['id' => $entry->id, 'part' => 'answer']);
    $errorUrl = route('backlog.entry.download', ['id' => $entry->id, 'part' => 'error']);

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->assertAttribute("a[href=\"{$requestUrl}\"]", 'href', $requestUrl)
        ->assertAttribute("a[href=\"{$responseUrl}\"]", 'href', $responseUrl)
        ->assertAttribute("a[href=\"{$answerUrl}\"]", 'href', $answerUrl)
        // No Error tab on a completed entry, so its download link is never rendered.
        ->assertNotPresent("a[href=\"{$errorUrl}\"]");
});

it('shows the protocol note only for a non-passthrough gateway', function () {
    $anthropicGateway = Gateway::factory()->anthropic()->create();
    $anthropicEntry = BacklogEntry::factory()->create(['gateway_id' => $anthropicGateway->id]);

    $ollamaGateway = Gateway::factory()->ollama()->create();
    $ollamaEntry = BacklogEntry::factory()->create(['gateway_id' => $ollamaGateway->id]);

    // The default seeded gateway is OpenAI-compatible, i.e. passthrough.
    $openAiEntry = BacklogEntry::factory()->create();

    [$page] = signIn();

    $page->navigate("/entries/{$anthropicEntry->id}")
        ->assertSee('Sent as Anthropic: the driver rewrites this body on the way out.');

    $page->navigate("/entries/{$ollamaEntry->id}")
        ->assertSee('Sent as Ollama: the driver rewrites this body on the way out.');

    $page->navigate("/entries/{$openAiEntry->id}")
        ->assertDontSee('the driver rewrites this body on the way out.');
});

it('renders an em dash in the Response tab for a queued entry with no output yet', function () {
    $entry = BacklogEntry::factory()->create();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->click(entryTab('Response'))
        ->wait(0.5)
        ->assertSeeIn('[id$="-panel-response"]', '—');
});

it('still renders gateway facts correctly when the answering gateway has been soft-deleted', function () {
    $gateway = Gateway::factory()->create(['name' => 'Retired Gateway']);
    $entry = BacklogEntry::factory()->completed()->create(['gateway_id' => $gateway->id]);

    $gateway->delete();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}")
        ->assertSee('Retired Gateway');
});

it('returns a working error page for an unknown entry id instead of a blank crash', function () {
    [$page] = signIn();

    $page->navigate('/entries/resp_does_not_exist')
        ->assertNoJavaScriptErrors();

    expect($page->content())->not->toBe('');
});

it('navigates back to the backlog via the "Back to backlog" link', function () {
    $entry = BacklogEntry::factory()->create();

    [$page] = signIn();

    // Matched as a selector rather than as bare text: the link reads
    // "← Back to backlog", and a click that waits for an exact text match
    // never resolves — it hangs the suite instead of failing it.
    $page->navigate("/entries/{$entry->id}")
        ->click('a:has-text("Back to backlog")')
        ->assertPathIs('/')
        ->assertSee('LLM Gateway');
});

it('copies the entry id to the clipboard via the CopyButton', function () {
    $entry = BacklogEntry::factory()->create();

    [$page] = signIn();

    $page->navigate("/entries/{$entry->id}");

    /*
     | Headless Chromium refuses `clipboard.writeText`, and the design system's
     | CopyButton awaits it before raising its toast — so left alone this asserts
     | a browser permission rather than the page. The capability is stubbed
     | instead, which also lets the test pin what was actually handed over
     | rather than only that something said it had been.
     */
    $page->script(<<<'JS'
        window.__copied = null;
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: (value) => { window.__copied = value; return Promise.resolve(); } },
        });
    JS);

    $page->assertAttribute('button[aria-label="Copy id"]', 'aria-label', 'Copy id')
        ->assertVisible('button[aria-label="Copy id"]')
        ->click('button[aria-label="Copy id"]')
        ->assertSee('Copied to clipboard');

    expect($page->script('window.__copied'))->toBe($entry->id);
});
