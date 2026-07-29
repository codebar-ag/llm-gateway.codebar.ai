<?php

declare(strict_types=1);

use App\Enums\BacklogStatus;

it('only allows cancelling queued entries', function (BacklogStatus $status, bool $cancellable) {
    expect($status->isCancellable())->toBe($cancellable);
})->with([
    [BacklogStatus::Queued, true],
    [BacklogStatus::Running, false],
    [BacklogStatus::Completed, false],
    [BacklogStatus::Failed, false],
    [BacklogStatus::Cancelled, false],
]);

it('renders human readable labels', function (BacklogStatus $status, string $label) {
    expect($status->label())->toBe($label);
})->with([
    [BacklogStatus::Queued, 'queued'],
    [BacklogStatus::Running, 'in progress'],
    [BacklogStatus::Completed, 'completed'],
    [BacklogStatus::Failed, 'failed'],
    [BacklogStatus::Cancelled, 'cancelled'],
]);

it('provides badge classes for every case', function (BacklogStatus $status) {
    expect($status->tone())->toBeIn(['neutral', 'info', 'success', 'warning', 'danger']);
})->with(BacklogStatus::cases());
