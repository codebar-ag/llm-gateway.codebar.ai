<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use App\Support\BacklogFilters;
use Illuminate\Http\Request;

function filtersFor(array $query): BacklogFilters
{
    return BacklogFilters::fromRequest(Request::create('/', 'GET', $query));
}

it('reads every filter off the query string', function () {
    $filters = filtersFor(['status' => 'completed', 'model' => ' m ', 'q' => ' resp_1 ', 'gateway' => ' g ']);

    expect($filters->status)->toBe(BacklogStatus::Completed)
        ->and($filters->model)->toBe('m')
        ->and($filters->search)->toBe('resp_1')
        ->and($filters->gateway)->toBe('g')
        ->and($filters->areEmpty())->toBeFalse();
});

it('reads an absent query string as no filter at all', function () {
    expect(filtersFor([])->areEmpty())->toBeTrue();
});

it('reads an unknown status as no status filter', function () {
    expect(filtersFor(['status' => 'nonsense'])->status)->toBeNull();
});

/* A malformed URL is not a filter: `(string) $array` would be a 500. */

it('ignores a filter sent as an array', function () {
    $filters = filtersFor(['model' => ['a', 'b'], 'q' => ['x'], 'gateway' => ['g'], 'status' => ['queued']]);

    expect($filters->areEmpty())->toBeTrue();
});

it('narrows the query by each filter', function () {
    $wanted = BacklogEntry::factory()->completed()->create(['model' => 'wanted']);
    BacklogEntry::factory()->create(['model' => 'other']);

    $matching = filtersFor(['status' => 'completed', 'model' => 'wanted'])->apply()->get();

    expect($matching->pluck('id')->all())->toBe([$wanted->id]);
});

it('matches an entry by an id prefix', function () {
    $entry = BacklogEntry::factory()->create();

    expect(filtersFor(['q' => substr($entry->id, 0, 12)])->apply()->count())->toBe(1);
});

/*
 | `%` and `_` are characters an operator typed, not pattern syntax. Unescaped,
 | a lone `%` matches every entry in the table.
 */

it('treats like wildcards in the search box as literal characters', function (string $search) {
    BacklogEntry::factory()->count(3)->create();

    expect(filtersFor(['q' => $search])->apply()->count())->toBe(0);
})->with(['%', 'resp%', '_', '\\', 'resp\\%']);
