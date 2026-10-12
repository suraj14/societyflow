<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A guard-roster rule that was not met (overlapping shift, inactive gate, a shift that has already started, ...).
 *
 * It is still an HTTP 422 for any API caller, but it names the form field it belongs to, so the admin screens can show
 * the reason next to that field - or as a message on the page - instead of a bare "422" error page.
 */
class ShiftAssignmentException extends HttpException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct(422, $message);
    }
}
