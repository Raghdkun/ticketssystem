<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;

/**
 * Platform-wide figures for a super admin.
 *
 * Shared by the admin owners screen and the dashboard so the two can never
 * disagree about how many tickets exist.
 */
final class PlatformStats
{
    /**
     * Platform activity, deliberately without money.
     *
     * What a venue takes at its own door is the venue's business. An
     * administrator can see scale -- how many owners, events and tickets --
     * without seeing anybody's income. The figures an owner sees are on the
     * owner dashboard, which an administrator reaches by impersonating, and
     * that leaves a record of who looked.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        return [
            // An owner is now simply an account that owns a venue, which
            // lets an administrator run one too.
            'owners' => User::whereHas('places')->count(),
            'banned' => User::whereNotNull('banned_at')->count(),
            'events' => Event::count(),
            'tickets' => Ticket::count(),
            'paid_tickets' => Ticket::where('status', TicketStatus::Paid)->count(),
            'pending_tickets' => Ticket::where('status', TicketStatus::Pending)->count(),
            'seats_paid' => (int) Ticket::where('status', TicketStatus::Paid)->sum('quantity'),
        ];
    }
}
