<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Models\BacklogEntry;
use App\Models\User;
use Tests\TestCase;

beforeEach(fn () => config()->set('gateway.dashboard.token', dashboardToken()));

/*
 | This file is not in the list Pest.php arranges both guards for, because the
 | last three tests here are what happens when a visitor holds neither. Every
 | other test asks for them explicitly.
 */

function asOperator(): TestCase
{
    return test()
        ->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->actingAs(User::factory()->create());
}

it('downloads the request as pretty-printed json', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'qwen3-vl:32b', 'messages' => [['role' => 'user', 'content' => 'ping']]],
    ]);

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']))
        ->assertOk()
        ->assertDownload($entry->id.'_request.json')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->streamedContent())->toBe(<<<'JSON'
        {
            "model": "qwen3-vl:32b",
            "messages": [
                {
                    "role": "user",
                    "content": "ping"
                }
            ]
        }
        JSON);
});

it('downloads the upstream output as json', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'response']))
        ->assertOk()
        ->assertDownload($entry->id.'_response.json')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->streamedContent())
        ->toContain('"text": "the answer is 42"')
        ->toContain("\n    ");
});

it('downloads the answer as markdown', function () {
    $entry = BacklogEntry::factory()->completed("# Heading\n\nthe answer is 42")->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertOk()
        ->assertDownload($entry->id.'_answer.md')
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');

    expect($response->streamedContent())->toBe("# Heading\n\nthe answer is 42");
});

it('downloads a structured answer as json', function () {
    $entry = BacklogEntry::factory()->completed('{"rechnungsnummer":"2026-4471","total":1284.50}')->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertOk()
        ->assertDownload($entry->id.'_answer.json')
        ->assertHeader('Content-Type', 'application/json');

    // Byte for byte what the model wrote: re-encoding to pretty-print it would
    // round 1284.50 to 1284.5 in a file someone may diff against the response.
    expect($response->streamedContent())->toBe('{"rechnungsnummer":"2026-4471","total":1284.50}');
});

it('downloads a prose answer that merely mentions braces as markdown', function () {
    $entry = BacklogEntry::factory()->completed('{ this is not json')->create();

    asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertOk()
        ->assertDownload($entry->id.'_answer.md')
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
});

it('downloads the error as json', function () {
    $entry = BacklogEntry::factory()->failed('upstream unavailable')->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'error']))
        ->assertOk()
        ->assertDownload($entry->id.'_error.json')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->streamedContent())->toContain('"message": "upstream unavailable"');
});

it('leaves slashes and unicode as they were written', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'qwen3-vl:32b', 'endpoint' => 'https://upstream.test/v1/chat', 'note' => 'grüß dich — 42'],
    ]);

    $content = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']))
        ->assertOk()
        ->streamedContent();

    expect($content)
        ->toContain('https://upstream.test/v1/chat')
        ->toContain('grüß dich — 42')
        ->not->toContain('\/')
        ->not->toContain('\u');
});

it('names the file after the entry id without decorating it', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    expect($entry->id)->toStartWith('resp_');

    asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertDownload($entry->id.'_answer.md');
});

it('refuses a part it does not know', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    asOperator()->get('/entries/'.$entry->id.'/download/headers')->assertNotFound();
});

it('refuses a part that has nothing in it', function (string $part) {
    $entry = BacklogEntry::factory()->create();

    asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => $part]))
        ->assertNotFound();
})->with(['response', 'answer', 'error']);

it('refuses the error of an entry that did not fail', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'error']))
        ->assertNotFound();
});

it('refuses an unknown entry', function () {
    asOperator()
        ->get(route('logs.entry.download', ['id' => 'resp_missing', 'part' => 'request']))
        ->assertNotFound();
});

/*
 | The file being handed over is the prompt itself, so both guards are asserted
 | here rather than left to the page tests: a download that answered without a
 | session would be a way around the dashboard's own login.
 */

it('never hands a prompt to a visitor without the token', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'test', 'messages' => [['role' => 'user', 'content' => 'invoice total']]],
    ]);

    $this->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']))
        ->assertForbidden()
        ->assertDontSee('invoice total');
});

it('never hands a prompt to a visitor who holds the token but no session', function () {
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'test', 'messages' => [['role' => 'user', 'content' => 'invoice total']]],
    ]);

    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken())
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']))
        ->assertRedirect(route('login'))
        ->assertDontSee('invoice total');
});

it('refuses a signed-in operator who lost the token', function () {
    $entry = BacklogEntry::factory()->completed()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertForbidden();
});

it('downloads an embedding answer as the summary the page shows', function () {
    $entry = BacklogEntry::factory()->embedded()->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertOk();

    expect($response->streamedContent())->toBe('2 embeddings × 4 dimensions');
});

it('downloads the vectors themselves as the response part', function () {
    $entry = BacklogEntry::factory()->embedded()->create();

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'response']))
        ->assertOk();

    expect($response->streamedContent())->toContain('"embedding"')
        ->toContain('0.1');
});

it('404s the answer of an embedding that has not been run yet', function () {
    // Not an empty file: "no answer yet" and "the answer was empty" are
    // different facts, and a queued entry has the first one.
    $entry = BacklogEntry::factory()->embeddings()->create();

    asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'answer']))
        ->assertNotFound();
});

it('streams an oversized request as its stored bytes rather than re-encoding it', function () {
    // Over the pretty-print threshold: decoding and re-encoding this to indent
    // it is the memory the web process does not have.
    $entry = BacklogEntry::factory()->create([
        'request' => ['model' => 'qwen3-vl:32b', 'image' => str_repeat('QUJDRA==', 200_000)],
    ]);

    $response = asOperator()
        ->get(route('logs.entry.download', ['id' => $entry->id, 'part' => 'request']))
        ->assertOk()
        ->assertDownload($entry->id.'_request.json')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->streamedContent())->toBe($entry->fresh()->getRawOriginal('request'));
});
