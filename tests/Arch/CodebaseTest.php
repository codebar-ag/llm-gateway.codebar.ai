<?php

declare(strict_types=1);

arch('no debug statements in application code')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('env() is only read from config, not from application code')
    ->expect('App')
    ->not->toUse('env');

arch('every application file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();
