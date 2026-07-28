<?php

use App\Enums\LocaleEnum;

it('has enum labels', function () {
    $enumLabels = collect(LocaleEnum::cases())
        ->flatMap(function (LocaleEnum $enum) {
            return [$enum->value => $enum->getLabel()];
        })->toArray();

    expect($enumLabels)
        ->toBeArray()
        ->toHaveCount(4)
        ->toBe([
            'de_CH' => 'German',
            'fr_CH' => 'French',
            'it_CH' => 'Italian',
            'en_CH' => 'English',
        ]);
})->group('unit', 'traits', 'labels');
