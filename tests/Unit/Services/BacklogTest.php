<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Http\Middleware\ResolveGateway;
use App\Jobs\ProcessBacklogEntry;
use App\Models\BacklogEntry;
use App\Models\Gateway;
use App\Services\Backlog;
use App\Services\Gateways;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->backlog = new Backlog;
});

/**
 * A request as it reaches the service: past the router, so the gateway
 * middleware has already worked out which upstream it is addressed to.
 */
function backlogRequest(
    array $headers = ['Authorization' => 'Bearer sk-test-caller'],
    ?Gateway $gateway = null,
): Request {
    $request = Request::create('/v1/chat/completions', 'POST');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    $request->attributes->set(ResolveGateway::ATTRIBUTE, $gateway ?? Gateway::default());

    return $request;
}

it('stores a queued entry and dispatches processing', function () {
    $entry = $this->backlog->add(backlogRequest(), ['model' => 'test-model', 'messages' => []]);

    expect($entry->id)->toStartWith('resp_')
        ->and($entry->status)->toBe(BacklogStatus::Queued)
        ->and($entry->model)->toBe('test-model')
        ->and($entry->request)->toBe(['model' => 'test-model', 'messages' => []])
        ->and($entry->api_key_hash)->toBe(hash('sha256', 'sk-test-caller'));

    Queue::assertPushed(ProcessBacklogEntry::class, fn (ProcessBacklogEntry $job) => $job->entryId === $entry->id);
});

it('keeps the payload byte for byte', function () {
    $payload = ['model' => 'm', 'anything' => ['deeply' => ['nested' => true]], 'n' => 1.5];

    expect($this->backlog->add(backlogRequest(), $payload)->request)->toBe($payload);
});

it('stores the caller headers', function () {
    $entry = $this->backlog->add(backlogRequest([
        'Authorization' => 'Bearer sk-caller',
        'X-Litellm-Tag' => 'invoices',
    ]), []);

    expect($entry->headers['authorization'])->toBe('Bearer sk-caller')
        ->and($entry->headers['x-litellm-tag'])->toBe('invoices');
});

it('records no model when the payload has none', function () {
    expect($this->backlog->add(backlogRequest(), ['messages' => []])->model)->toBeNull();
});

it('records no model for a non scalar model field', function () {
    expect($this->backlog->add(backlogRequest(), ['model' => ['a' => 'b']])->model)->toBeNull();
});

it('records no key hash without authorization', function () {
    expect($this->backlog->add(backlogRequest([]), [])->api_key_hash)->toBeNull();
});

it('takes max attempts from the configuration', function () {
    config(['gateway.backlog.max_attempts' => 4]);

    expect($this->backlog->add(backlogRequest(), [])->max_attempts)->toBe(4);
});

it('queues onto the configured queue', function () {
    config(['gateway.backlog.queue' => 'custom-queue']);

    $this->backlog->add(backlogRequest(), []);

    Queue::assertPushedOn('custom-queue', ProcessBacklogEntry::class);
});

/*
 | Deliberately reads the shipped default instead of setting one. The worker
 | is started with an explicit `--queue=` in the deploy plists and the README,
 | and a worker listening on a queue nothing dispatches to looks healthy while
 | the backlog never drains.
 */
it('defaults to the default queue so the documented worker picks the work up', function () {
    expect(config('gateway.backlog.queue'))->toBe('default');
});

it('generates unique ids', function () {
    $a = $this->backlog->add(backlogRequest(), []);
    $b = $this->backlog->add(backlogRequest(), []);

    expect($a->id)->not->toBe($b->id)
        ->and(BacklogEntry::count())->toBe(2);
});

it('records the gateway the request was addressed to', function () {
    $gateway = Gateway::factory()->create();

    $entry = $this->backlog->add(backlogRequest(gateway: $gateway), ['model' => 'm']);

    expect($entry->gateway_id)->toBe($gateway->id)
        ->and($entry->gateway->name)->toBe($gateway->name);
});

it('freezes the gateway rather than resolving it again later', function () {
    $gateway = Gateway::factory()->create();

    $entry = $this->backlog->add(backlogRequest(gateway: $gateway), ['model' => 'm']);

    (new Gateways)->makeDefault(Gateway::factory()->create());

    expect($entry->fresh()->gateway_id)->toBe($gateway->id);
});

it('names the gateway default model when the caller left one out', function () {
    $gateway = Gateway::factory()->create(['default_model' => 'llama3.2:3b']);

    $entry = $this->backlog->add(backlogRequest(gateway: $gateway), ['messages' => []]);

    expect($entry->model)->toBe('llama3.2:3b')
        ->and($entry->request['model'])->toBe('llama3.2:3b');
});

it('never overrides a model the caller named', function () {
    $gateway = Gateway::factory()->create(['default_model' => 'llama3.2:3b']);

    $entry = $this->backlog->add(backlogRequest(gateway: $gateway), ['model' => 'qwen3-vl:32b']);

    expect($entry->model)->toBe('qwen3-vl:32b')
        ->and($entry->request['model'])->toBe('qwen3-vl:32b');
});
