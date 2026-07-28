<?php

use App\Nova\Traits\NovaTimestampsPanelTrait;
use Laravel\Nova\Panel;

it('builds timestamps panel', function () {
    $obj = new class
    {
        use NovaTimestampsPanelTrait;
    };

    $panel = $obj->timestampsPanel(displayCreatedAt: true, displayUpdatedAt: true, displayDeletedAt: true);

    expect($panel)->toBeInstanceOf(Panel::class);
})->group('unit', 'nova', 'traits');

it('can hide timestamp fields', function () {
    $obj = new class
    {
        use NovaTimestampsPanelTrait;
    };

    $panel = $obj->timestampsPanel(displayCreatedAt: false, displayUpdatedAt: false, displayDeletedAt: false);

    expect($panel)->toBeInstanceOf(Panel::class);
})->group('unit', 'nova', 'traits');
