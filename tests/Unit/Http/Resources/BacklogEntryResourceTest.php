<?php

declare(strict_types=1);

use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
use App\Support\QueuePlace;
use Illuminate\Http\Request;

function shapeOf(BacklogEntry $entry): array
{
    return (new BacklogEntryResource($entry))->toArray(Request::create('/'));
}

it('serializes to the responses api shape', function () {
    $entry = BacklogEntry::factory()->completed('hello')->create(['model' => 'test-model']);

    expect(shapeOf($entry))->toMatchArray([
        'id' => $entry->id,
        'object' => 'response',
        'created_at' => $entry->created_at->timestamp,
        'status' => 'completed',
        'model' => 'test-model',
        'error' => null,
    ])->and(shapeOf($entry))->not->toHaveKey('metadata');
});

it('serializes a missing output as an empty array', function () {
    $entry = BacklogEntry::factory()->create();

    expect(shapeOf($entry)['output'])->toBe([])
        ->and(shapeOf($entry)['usage'])->toBeNull();
});

it('carries the answer text through', function () {
    $entry = BacklogEntry::factory()->completed('the answer is 42')->create();

    expect(shapeOf($entry)['output'][0]['content'][0]['text'])->toBe('the answer is 42');
});

it('reports no queue unless a place is given', function () {
    $entry = BacklogEntry::factory()->create();

    expect(shapeOf($entry))->toHaveKey('queue')
        ->and(shapeOf($entry)['queue'])->toBeNull();
});

it('renders a place under its own key, leaving the spec fields alone', function () {
    $entry = BacklogEntry::factory()->create();

    $shape = (new BacklogEntryResource($entry))
        ->withPlace(new QueuePlace(
            position: 3,
            depth: 7,
            workers: 2,
            estimatedSeconds: 84,
            estimatedCompletionAt: 1785000084,
        ))
        ->toArray(Request::create('/'));

    expect($shape['queue'])->toBe([
        'position' => 3,
        'depth' => 7,
        'workers' => 2,
        'estimated_seconds' => 84,
        'estimated_completion_at' => 1785000084,
    ])->and($shape['id'])->toBe($entry->id);
});

it('survives being built through collection(), which passes the key along', function () {
    $entries = BacklogEntry::factory()->count(2)->create();

    $shapes = BacklogEntryResource::collection($entries)->resolve(Request::create('/'));

    expect($shapes)->toHaveCount(2)
        ->and($shapes[0]['queue'])->toBeNull()
        ->and($shapes[1]['queue'])->toBeNull();
});
