<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A profile picture could not be stored (bad upload, unsupported type, disk
 * problem). The message is written for the end user and is safe to show;
 * anything else that goes wrong while saving a profile is not this type and
 * is never echoed back to the client.
 */
class ProfileImageException extends RuntimeException
{
}
