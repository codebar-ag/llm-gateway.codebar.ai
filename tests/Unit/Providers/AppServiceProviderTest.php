<?php

declare(strict_types=1);

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/*
 | RequestException::dontTruncate() is set once at boot. Nothing downstream
 | of it (UpstreamFailure::describe(), for instance) ever calls
 | $exception->getMessage() on the upstream branch, so this is the only place
 | that would notice if the boot() call were ever removed.
 */

it('never truncates a RequestException message, even for a long body', function () {
    $long = str_repeat('x', 500);
    Http::fake(['*' => Http::response($long, 500)]);

    try {
        Http::get('http://upstream.test')->throw();

        $this->fail('Expected a RequestException.');
    } catch (RequestException $exception) {
        expect($exception->getMessage())->toContain($long);
    }
});
