<?php

use App\Enums\ProviderEnum;

it('returns correct labels array', function () {
    expect(ProviderEnum::MICROSOFT_OFFICE_365->getLabel())->toBe(__('Microsoft Office 365'));
})->group('enums', 'environment-enum');

it('returns correct cases array', function () {

    $expectedCases = [
        ProviderEnum::MICROSOFT_OFFICE_365,
    ];

    expect(ProviderEnum::cases())->toBe($expectedCases);

})->group('enums', 'environment-enum');
