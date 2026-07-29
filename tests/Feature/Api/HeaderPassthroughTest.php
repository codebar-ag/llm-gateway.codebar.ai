<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyDashboardToken;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Services\Upstream\Upstreams;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('queues without any authorization at all', function () {
    Queue::fake();

    $response = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x']);

    $response->assertStatus(202);
    expect(BacklogEntry::find($response->json('id'))->api_key_hash)->toBeNull();
});

it('lets a request without a key fail upstream like any other', function () {
    Queue::fake();
    config(['gateway.backlog.max_attempts' => 1]);
    Http::fake(['*/v1/chat/completions' => Http::response([
        'error' => ['message' => 'Authentication Error'],
    ], 401)]);

    $id = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'])->json('id');

    $job = new ProcessBacklogEntry($id);

    try {
        $job->handle(new Upstreams);
    } catch (RequestException $exception) {
        $job->failed($exception);
    }

    expect(BacklogEntry::find($id)->refresh()->error['status'])->toBe(401);
});

it('stores every caller header', function () {
    Queue::fake();

    $id = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], [
        'Authorization' => 'Bearer sk-caller',
        'X-Litellm-Tag' => 'invoices',
        'X-Request-Id' => 'req-42',
    ])->json('id');

    $headers = BacklogEntry::find($id)->headers;

    expect($headers['authorization'])->toBe('Bearer sk-caller')
        ->and($headers['x-litellm-tag'])->toBe('invoices')
        ->and($headers['x-request-id'])->toBe('req-42');
});

it('stores the headers encrypted', function () {
    Queue::fake();

    $id = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], [
        'Authorization' => 'Bearer sk-caller',
    ])->json('id');

    $stored = DB::table('backlog_entries')->where('id', $id)->value('headers');

    expect($stored)->not->toContain('sk-caller')->not->toBeNull();
});

it('replays every header upstream', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], [
        'Authorization' => 'Bearer sk-caller',
        'X-Litellm-Tag' => 'invoices',
    ]);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-caller')
        && $request->hasHeader('X-Litellm-Tag', 'invoices'));
});

it('replays a different key for every caller', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], ['Authorization' => 'Bearer sk-alice']);
    $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], ['Authorization' => 'Bearer sk-bob']);

    $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('Authorization')[0]);

    expect($sent)->toContain('Bearer sk-alice')->toContain('Bearer sk-bob');
});

it('never leaks headers through the api', function () {
    Queue::fake();

    $caller = ['Authorization' => 'Bearer sk-caller'];

    $id = $this->postJson('/v1/responses', ['model' => 'm', 'input' => 'x'], $caller)->json('id');

    $this->getJson("/v1/responses/{$id}", $caller)->assertOk()->assertDontSee('sk-caller');
    $this->getJson('/v1/responses', $caller)->assertOk()->assertDontSee('sk-caller');
});

it('never leaks headers on the dashboard', function () {
    config()->set('gateway.dashboard.token', dashboardToken());
    $this->withCookie(VerifyDashboardToken::COOKIE, dashboardToken());

    $entry = BacklogEntry::factory()->create();

    $this->get('/')->assertOk()->assertDontSee('sk-test-caller');
    $this->get(route('backlog.entry', $entry->id))
        ->assertOk()
        ->assertDontSee('sk-test-caller')
        ->assertSee('authorization');
});

it('clears the headers once the entry is final', function (string $transition) {
    // Each ending starts from the state it is actually reached from: a
    // cancellation from queued, the other two from a claimed entry.
    $entry = $transition === 'cancelled'
        ? BacklogEntry::factory()->create()
        : BacklogEntry::factory()->running()->create();

    expect($entry->headers)->not->toBeNull();

    match ($transition) {
        'completed' => $entry->markCompleted('hi', []),
        'failed' => $entry->markFailed(new RuntimeException('boom')),
        'cancelled' => $entry->cancelIfQueued(),
    };

    expect($entry->refresh()->headers)->toBeNull()
        ->and($entry->api_key_hash)->not->toBeNull();
})->with(['completed', 'failed', 'cancelled']);

it('keeps the headers while the entry is retried', function () {
    Queue::fake();

    $entry = BacklogEntry::factory()->running()->create(['attempts' => 1, 'max_attempts' => 3]);

    (new ProcessBacklogEntry($entry->id))->failed(new RuntimeException('boom'));

    expect($entry->refresh()->headers)->not->toBeNull();
});

it('sends nothing of its own when the headers were cleared', function () {
    Http::fake(['*/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    $entry = BacklogEntry::factory()->create(['headers' => null]);

    (new ProcessBacklogEntry($entry->id))->handle(new Upstreams);

    Http::assertSent(fn ($request) => $request->header('Authorization') === []);
});
