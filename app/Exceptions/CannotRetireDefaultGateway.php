<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Refused: something has to answer requests that carry no gateway id.
 *
 * A type of its own rather than a bare RuntimeException, so the dashboard can
 * catch this one case and let every other runtime failure reach the handler
 * instead of being reported to an operator as a routing-table complaint.
 */
class CannotRetireDefaultGateway extends RuntimeException
{
    public static function make(): self
    {
        return new self('Make another gateway the default before deleting this one.');
    }
}
