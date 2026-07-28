<?php

use App\Helpers\Facades\HelperDate;

it('helper date: format date-time', function () {
    $date = now();

    $formatted = HelperDate::formatDateTime($date, 'de');
    expect($formatted)->toBe($date->format('d.m.Y H:i').' Uhr');

    $formatted = HelperDate::formatDateTime($date, 'en');
    expect($formatted)->toBe($date->format('d.m.Y g:i a'));

    $formatted = HelperDate::formatDateTime($date, 'default');
    expect($formatted)->toBe($date->format('d.m.Y H:i'));
})->group('date');

it('date: format date', function () {
    $date = now();
    $formatted = HelperDate::formatDate($date);
    expect($formatted)->toBe($date->format('d.m.Y'));
})->group('helper', ' date');
