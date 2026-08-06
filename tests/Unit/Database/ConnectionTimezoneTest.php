<?php

declare(strict_types=1);

/*
 | The bug this guards is invisible under the test suite, which runs on SQLite:
 | SQLite stores a datetime as the text Eloquent hands it and gives the same
 | text back, so a round trip is exact whatever anyone believes the timezone to
 | be. Postgres resolves that same bare text against the *session* timezone —
 | and unset, the session inherits whatever the server defaults to. A host an
 | hour off Europe/Zurich then stored every timestamp an hour from the instant
 | it meant, and the dashboard read those entries back as an hour in the future.
 |
 | So this asserts on the configuration rather than on a round trip. It is the
 | one place the two zones are required to agree.
 */

it('pins the Postgres session timezone to the application timezone', function () {
    expect(config('database.connections.pgsql.timezone'))->toBe(config('app.timezone'));
});
