<?php

use App\Nova\Traits\NovaIdentificationPanelTrait;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

it('builds identification panel with id and uuid fields', function () {
    $obj = new class
    {
        use NovaIdentificationPanelTrait;
    };

    $panel = $obj->identificationPanel(displayId: true, displayUuid: true, limit: 2);

    expect($panel)->toBeInstanceOf(Panel::class);
})->group('unit', 'nova', 'traits');

it('includes extra fields in identification panel', function () {
    $obj = new class
    {
        use NovaIdentificationPanelTrait;
    };

    $panel = $obj->identificationPanel(displayId: false, displayUuid: false, fields: []);

    expect($panel)->toBeInstanceOf(Panel::class);
})->group('unit', 'nova', 'traits');

it('appends custom fields inside the identification panel', function () {
    $obj = new class
    {
        use NovaIdentificationPanelTrait;
    };

    $extra = Text::make('Extra', 'extra');

    $panel = $obj->identificationPanel(displayId: true, displayUuid: false, fields: [$extra]);

    expect($panel)->toBeInstanceOf(Panel::class);
})->group('unit', 'nova', 'traits');
