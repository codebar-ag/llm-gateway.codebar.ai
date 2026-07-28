<?php

use App\Enums\SessionKeyEnum;

it('returns correct labels array', function () {
    expect(SessionKeyEnum::LANGUAGE->getLabel())->toBe(__('Language'));
})->group('enums', 'session-key-enum');

it('returns correct cases array', function () {
    $expectedCases = [
        SessionKeyEnum::LANGUAGE,
    ];

    expect(SessionKeyEnum::cases())->toBe($expectedCases);
})->group('enums', 'session-key-enum');
