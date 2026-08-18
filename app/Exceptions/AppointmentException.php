<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an appointment cannot be made. The message is a translation key
 * so it can be surfaced to the visitor in their own language.
 */
class AppointmentException extends RuntimeException
{
    public static function closed(): self
    {
        return new self('tickets.errors.closed');
    }

    public static function soldOut(int $remaining): self
    {
        return new self($remaining > 0 ? 'tickets.errors.not_enough_seats' : 'tickets.errors.sold_out');
    }

    public static function rulesNotAccepted(): self
    {
        return new self('tickets.errors.rules_not_accepted');
    }

    public static function tooMany(): self
    {
        return new self('tickets.errors.too_many');
    }
}
