<?php

use App\Enums\RoleEnum;

it('returns correct labels array', function () {
    expect(RoleEnum::ADMINISTRATOR->getLabel())->toBe(__('enums.role.administrator'))
        ->and(RoleEnum::USER->getLabel())->toBe(__('enums.role.user'));
})->group('enums', 'role-enum');

it('returns correct cases array', function () {

    $expectedCases = [
        RoleEnum::ADMINISTRATOR,
        RoleEnum::USER,
    ];

    expect(RoleEnum::cases())->toBe($expectedCases);

})->group('enums', 'role-enum');
