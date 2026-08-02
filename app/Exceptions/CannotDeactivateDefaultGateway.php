<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Refused: something has to answer requests that carry no gateway id.
 *
 * The sibling of `CannotRetireDefaultGateway`, for the other way the same
 * invariant used to break. Routing reads `Gateway::activeDefault()`, which
 * wants both flags — so clearing `is_active` on the row holding the default
 * takes the root API down exactly as deleting it would, only quietly: every
 * request without a gateway id starts answering 503 while the dashboard
 * reports the edit as saved.
 */
class CannotDeactivateDefaultGateway extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'This gateway takes all requests that arrive without a gateway id. '
            .'Make another gateway the default first, then deactivate this one.'
        );
    }
}
