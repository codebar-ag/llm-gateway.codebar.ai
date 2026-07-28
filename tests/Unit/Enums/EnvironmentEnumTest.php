<?php

use App\Enums\EnvironmentEnum;

it('returns correct labels array', function () {
    expect(EnvironmentEnum::LOCAL->getLabel())->toBe(__('Local'))
        ->and(EnvironmentEnum::STAGING->getLabel())->toBe(__('Staging'))
        ->and(EnvironmentEnum::PRODUCTION->getLabel())->toBe(__('Production'));
})->group('enums', 'environment-enum');

it('returns correct cases array', function () {

    $expectedCases = [
        EnvironmentEnum::LOCAL,
        EnvironmentEnum::STAGING,
        EnvironmentEnum::PRODUCTION,
    ];

    expect(EnvironmentEnum::cases())->toBe($expectedCases);

})->group('enums', 'environment-enum');
