<?php

declare(strict_types=1);

dataset('classnames', [
    ['Actions', 'Action'],
    ['Console', 'Command'],
    ['DTOs', 'DTO'],
    ['Enums', 'Enum'],
    ['Events', 'Event'],
    // Helpers: classes are HelperFoo, not FooHelper — skipped for the global *Helper suffix rule.
    ['Http\Controllers', 'Controller'],
    ['Interfaces', 'Interface'],
    ['Jobs', 'Job'],
    ['Listeners', 'Listener'],
    ['Middleware', 'Middleware'],
    ['Models', ''],
    ['Notifications', 'Notification'],
    ['Observers', 'Observer'],
    ['Policies', 'Policy'],
    ['Providers', 'Provider'],
    ['Requests', 'Request'],
    ['Rules', 'Rule'],
    ['Services', 'Service'],
    ['Traits', 'Trait'],
    ['Views', 'View'],
]);

it('has suffix', function ($namespace, $suffix) {
    expect("App\\{$namespace}")
        ->classes()
        ->toHaveSuffix($suffix);
})->with('classnames');

it('every class is correctly named', function ($namespace, $suffix) {
    $directory = __DIR__.'/../../app/'.str_replace('App\\', '', str_replace('\\', '/', $namespace));

    if (! is_dir($directory)) {
        $this->markTestSkipped("Directory does not exist: {$directory}");
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.php')) {
            $paths[] = $fileInfo->getPathname();
        }
    }

    sort($paths);

    if ($paths === []) {
        $this->markTestSkipped("No PHP classes in {$directory}");
    }

    foreach ($paths as $path) {
        $relative = substr($path, strlen($directory) + 1);
        $relative = str_replace('\\', '/', $relative);
        $classSuffix = str_replace('/', '\\', substr($relative, 0, -4));
        $className = "App\\{$namespace}\\{$classSuffix}";
        expect($className)->toHaveSuffix($suffix);
    }
})->with('classnames');
