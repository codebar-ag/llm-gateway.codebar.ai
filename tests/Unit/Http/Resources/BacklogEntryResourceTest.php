<?php

declare(strict_types=1);

use App\Http\Resources\BacklogEntryResource;
use App\Models\BacklogEntry;
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
