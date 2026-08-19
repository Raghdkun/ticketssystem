<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';

    /**
     * Statuses that occupy seats against an event's total quantity.
     *
     * @return array<int, string>
     */
    public static function seatHolding(): array
    {
        return [self::Pending->value, self::Paid->value];
    }

    /**
     * Statuses where the holder never came through the door. A no-show was
     * paid for or held and simply not used; a cancellation was called off.
     *
     * Returns cases rather than values: models cast this column, so comparing
     * an in-memory collection against raw strings silently matches nothing.
     *
     * @return array<int, self>
     */
    public static function unattended(): array
    {
        return [self::NoShow, self::Expired, self::Cancelled];
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Expired, self::NoShow], true);
    }
}
