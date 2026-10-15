<?php

namespace App\Services;

use RuntimeException;

/**
 * A gate-workflow action that cannot happen, with the HTTP status that describes why (403 not allowed, 404 not visible,
 * 409 someone else got there first or it is in the wrong state, 422 a required input is missing). The message is plain
 * language for the person who tapped the button.
 */
class VisitorWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly ?string $currentStatus = null)
    {
        parent::__construct($message);
    }
}
