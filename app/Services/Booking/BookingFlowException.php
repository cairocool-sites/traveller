<?php

namespace App\Services\Booking;

use RuntimeException;

class BookingFlowException extends RuntimeException
{
    public static function invalidRate(string $message = 'The selected rate is no longer available.'): self
    {
        return new self($message);
    }
}
