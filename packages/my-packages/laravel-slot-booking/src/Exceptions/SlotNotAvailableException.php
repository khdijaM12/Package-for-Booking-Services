<?php

namespace Khadija\LaravelSlotBooking\Exceptions;

use Exception;

class SlotNotAvailableException extends Exception
{
    public function __construct($message = "The requested slot is not available for booking.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}