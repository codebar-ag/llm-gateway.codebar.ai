<?php

use App\Helpers\Facades\HelperMoney;

it('helper money: format', function () {
    $number = 1234.56;
    $formatted = HelperMoney::format($number);
    expect($formatted)->toBe("1'234.56 CHF");

    $number = null;
    $formatted = HelperMoney::format($number);
    expect($formatted)->toBe('0.00 CHF');
})->group('helper', 'money');

it('helper money: format-left', function () {
    $number = 1234.56;
    $formatted = HelperMoney::formatLeft($number);
    expect($formatted)->toBe("CHF 1'234.56");

    $number = null;
    $formatted = HelperMoney::format($number);
    expect($formatted)->toBe('0.00 CHF');
})->group('helper', 'money');

it('helper money: roundMoney', function () {
    $money = 12.13;
    $rounded = HelperMoney::roundMoney($money);
    expect($rounded)->toBe(12.15);
})->group('helper', 'money');

it('helper money: roundMoneyUp', function () {
    $money = 12.12;
    $rounded = HelperMoney::roundMoneyUp($money);
    expect($rounded)->toBe(12.15);
})->group('helper', 'money');

it('helper money: roundMoneyDown', function () {
    $money = 12.13;
    $rounded = HelperMoney::roundMoneyDown($money);
    expect($rounded)->toBe(12.10);
})->group('helper', 'money');
