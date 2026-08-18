<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Statuses that occupy seats against an event's total quantity.
     *
     * @return array<int, string>
     */
    public static function seatHolding(): array
    {
        return [self::Pending->value, self::Paid->value];
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Expired], true);
    }
}
