<?php

declare(strict_types=1);

use App\Support\Duration;

/*
 | Every boundary between two renderings, from both sides: the unit a span is
 | said in changes four times, and each change is one comparison away from
 | reading a full hour as "60m" or a full day as "24h".
 */

it('says a span at the coarsest two units that describe it', function (?int $seconds, string $expected) {
    expect(Duration::human($seconds))->toBe($expected);
})->with([
    'nothing measured' => [null, '—'],
    'no time at all' => [0, '0s'],
    'one second' => [1, '1s'],
    'last second before a minute' => [59, '59s'],
    'exactly a minute' => [60, '1m'],
    'a minute and a second' => [61, '1m 1s'],
    'just under two minutes' => [119, '1m 59s'],
    'exactly two minutes' => [120, '2m'],
    'just under an hour' => [3599, '59m 59s'],
    'exactly an hour' => [3600, '1h'],
    'an hour and a bit' => [3661, '1h 1m'],
    'just under a day' => [86399, '23h 59m'],
    'exactly a day' => [86400, '1d'],
    'a day and an hour' => [90000, '1d 1h'],
    'a clock running backwards' => [-3, '0s'],
]);

it('gives the unrounded measurement for a tooltip', function (?int $seconds, ?string $expected) {
    expect(Duration::exact($seconds))->toBe($expected);
})->with([
    'nothing measured' => [null, null],
    'no time at all' => [0, '0s'],
    'one second' => [1, '1s'],
    'last second before a minute' => [59, '59s'],
    'exactly a minute' => [60, '60s'],
    'a minute and a second' => [61, '61s'],
    'just under two minutes' => [119, '119s'],
    'exactly two minutes' => [120, '120s'],
    'just under an hour' => [3599, '3599s'],
    'exactly an hour' => [3600, '3600s'],
    'an hour and a bit' => [3661, '3661s'],
    'just under a day' => [86399, '86399s'],
    'exactly a day' => [86400, '86400s'],
    'a day and an hour' => [90000, '90000s'],
    'a clock running backwards' => [-3, '0s'],
]);

it('drops the smaller unit only when it is zero', function () {
    expect(Duration::human(1554))->toBe('25m 54s')
        ->and(Duration::human(7200))->toBe('2h')
        ->and(Duration::human(172800))->toBe('2d');
});
