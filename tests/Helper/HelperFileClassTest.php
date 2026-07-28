
<?php

use App\Helpers\Facades\HelperFile;

it('helper file: format bytes', function () {
    $byte = 1;
    $formatted = HelperFile::formatBytes($byte);
    expect($formatted)
        ->toBeString()
        ->toEqual('1 B');

    $kiloByte = 1024;
    $formatted = HelperFile::formatBytes($kiloByte);
    expect($formatted)
        ->toBeString()
        ->toEqual('1 KB');

    $megaByte = 1048576;
    $formatted = HelperFile::formatBytes($megaByte);
    expect($formatted)
        ->toBeString()
        ->toEqual('1 MB');

    $gigaByte = 1073741824;
    $formatted = HelperFile::formatBytes($gigaByte);
    expect($formatted)
        ->toBeString()
        ->toEqual('1 GB');

    $teraByte = 1099511627776;
    $formatted = HelperFile::formatBytes($teraByte);
    expect($formatted)
        ->toBeString()
        ->toEqual('1 TB');
})->group('helper', 'file');

it('helper file: name with date', function () {
    $name = 'filename';

    $formatted = HelperFile::nameWithDate($name);
    expect($formatted)
        ->toBeString()
        ->toEqual(now()->format('Ymd_').$name);
})->group('file');

it('helper file: name with date time', function () {
    $name = 'filename';

    $formatted = HelperFile::nameWithDateTime($name);
    expect($formatted)
        ->toBeString()
        ->toEqual(now()->format('YmdHm_').$name);
})->group('helper', 'file');
