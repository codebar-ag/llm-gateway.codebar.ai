<?php

use App\Support\CspAllowlist;

beforeEach(function () {
    $this->cspPath = config_path('csp-allowlists.json');
    $this->hadFile = is_file($this->cspPath);
    $this->backup = $this->hadFile ? file_get_contents($this->cspPath) : null;
});

afterEach(function () {
    if ($this->hadFile) {
        file_put_contents($this->cspPath, $this->backup);
    } elseif (is_file($this->cspPath)) {
        unlink($this->cspPath);
    }
});

it('returns empty list when config file is missing', function () {
    if (is_file($this->cspPath)) {
        unlink($this->cspPath);
    }

    expect(CspAllowlist::sources('script'))->toBe([]);
})->group('unit', 'support');

it('returns empty list for invalid json', function () {
    file_put_contents($this->cspPath, 'not-json');

    expect(CspAllowlist::sources('script'))->toBe([]);
})->group('unit', 'support');

it('returns directive sources from valid json', function () {
    $data = [
        'script' => ['https://example.com/app.js'],
        'connect' => ['https://api.example.com'],
    ];
    file_put_contents($this->cspPath, json_encode($data));

    expect(CspAllowlist::sources('script'))->toBe(['https://example.com/app.js'])
        ->and(CspAllowlist::sources('missing'))->toBe([]);
})->group('unit', 'support');
