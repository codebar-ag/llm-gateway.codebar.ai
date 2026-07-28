<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Every class, enum, and trait under app/ must appear in at least one *Test.php file name.
 */
it('has a dedicated test file name for each app class enum and trait', function () {
    $finder = Finder::create()
        ->files()
        ->in(__DIR__.'/../../app')
        ->name('*.php');

    $testBasenames = collect(
        Finder::create()
            ->files()
            ->in(__DIR__.'/../../tests')
            ->name('*Test.php')
    )->map(fn (SplFileInfo $f) => $f->getFilename())->all();

    $missing = [];

    foreach ($finder as $file) {
        $path = $file->getRealPath();
        if ($path === false) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/^\s*interface\s+/m', $contents)) {
            continue;
        }

        $shortName = null;
        if (preg_match('/^(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $contents, $m)) {
            $shortName = $m[1];
        } elseif (preg_match('/^enum\s+(\w+)/m', $contents, $m)) {
            $shortName = $m[1];
        } elseif (preg_match('/^trait\s+(\w+)/m', $contents, $m)) {
            $shortName = $m[1];
        }

        if ($shortName === null) {
            continue;
        }

        $matched = false;
        foreach ($testBasenames as $testFile) {
            if (str_contains($testFile, $shortName)) {
                $matched = true;

                break;
            }
        }

        if (! $matched) {
            $missing[] = $shortName.' ('.$file->getRelativePathname().')';
        }
    }

    expect($missing)->toBe([]);
})->group('core', 'architecture');
