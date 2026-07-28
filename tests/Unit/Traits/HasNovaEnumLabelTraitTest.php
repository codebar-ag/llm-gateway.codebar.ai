<?php

use App\Traits\HasNovaEnumLabelTrait;

it('exposes label via trait', function () {
    $enum = new class
    {
        use HasNovaEnumLabelTrait;

        public function getLabel(): string
        {
            return 'Test Label';
        }
    };

    expect($enum->label())->toBe('Test Label');
})->group('unit', 'traits');
