<?php

namespace Khadija\LaravelSlotBooking\Exceptions;

use Exception;

class SlotAlreadyBookedException extends Exception
{
    public function __construct($message = "The requested slot has already been booked.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}