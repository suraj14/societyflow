<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking that cannot be made, with a message written for the resident and
 * the HTTP status the API should answer with (404 unknown facility, 422 a
 * rule or a taken slot, 403 not allowed to book).
 */
class FacilityBookingException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
