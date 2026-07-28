<?php

use App\Helpers\Facades\HelperBank;

it('helper bank: format iban', function () {
    $iban = 'DE91111111110123456789';

    $formattedIban = HelperBank::formatIban($iban);
    expect($formattedIban)
        ->toBeString()
        ->toEqual('DE91 1111 1111 0123 4567 89');
})->group('helper', 'bank');
