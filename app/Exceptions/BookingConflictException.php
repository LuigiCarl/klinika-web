<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class BookingConflictException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The selected appointment time is no longer available.', previous: $previous);
    }
}
