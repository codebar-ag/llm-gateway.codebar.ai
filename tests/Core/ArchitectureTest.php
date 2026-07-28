<?php

it('will not use any debug function')
    ->expect(['dd', 'ray', 'dump'])
    ->each->not()->toBeUsed();
