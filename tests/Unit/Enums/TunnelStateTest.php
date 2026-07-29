<?php

declare(strict_types=1);

use App\Enums\TunnelState;

it('has a label for every state', function (TunnelState $state, string $label) {
    expect($state->label())->toBe($label);
})->with([
    [TunnelState::Ready, 'tunnel up'],
    [TunnelState::Down, 'tunnel down'],
    [TunnelState::Unknown, 'tunnel unknown'],
]);

it('has badge classes for every state', function (TunnelState $state) {
    expect($state->tone())->toBeIn(['emerald', 'red', 'neutral']);
})->with(TunnelState::cases());

it('uses distinct colours per state', function () {
    $classes = collect(TunnelState::cases())->map->tone();

    expect($classes->unique())->toHaveCount(count(TunnelState::cases()));
});
