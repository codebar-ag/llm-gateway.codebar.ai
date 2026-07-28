<?php

it('configures Lasso for Vite and the s3 disk', function () {
    expect(config('lasso.compiler.script'))->toBe('npm run build')
        ->and(config('lasso.storage.disk'))->toBe('s3')
        ->and(config('filesystems.disks.'.config('lasso.storage.disk')))->toBeArray()
        ->and(config('filesystems.disks.s3.driver'))->toBe('s3')
        ->and(config('filesystems.disks.s3.region'))->toBeString()->not->toBeEmpty();
})->group('unit', 'config');
