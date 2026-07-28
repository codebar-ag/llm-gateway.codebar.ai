<?php

use App\Enums\GuardEnum;

it('returns correct labels array', function () {
    expect(GuardEnum::WEB->getLabel())->toBe(__('Web'));
})->group('enums', 'guard-enum');

it('returns correct cases array', function () {

    $expectedCases = [
        GuardEnum::WEB,
    ];

    expect(GuardEnum::cases())->toBe($expectedCases);

})->group('enums', 'guard-enum');
