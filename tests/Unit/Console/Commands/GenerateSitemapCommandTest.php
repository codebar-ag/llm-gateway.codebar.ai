<?php

use Illuminate\Support\Facades\Artisan;

it('generates sitemap file', function () {
    $path = public_path('sitemap.xml');
    if (is_file($path)) {
        unlink($path);
    }

    Artisan::call('sitemap:generate');

    expect(is_file($path))->toBeTrue();
    unlink($path);
})->group('unit', 'console');
