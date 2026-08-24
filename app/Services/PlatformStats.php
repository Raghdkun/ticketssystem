<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide figures for a super admin.
 *
 * Shared by the admin owners screen and the dashboard so the two can never
 * disagree about how many tickets exist.
 */
final class PlatformStats
{
    /**
     * @return array<string, int|float>
     */
    public function all(): array
    {
        $revenue = Ticket::query()
            ->join('events', 'events.id', '=', 'tickets.event_id')
            ->where('tickets.status', TicketStatus::Paid)
            ->sum(DB::raw('tickets.quantity * events.price'));

        return [
            'owners' => User::where('role', UserRole::Owner)->count(),
            'banned' => User::whereNotNull('banned_at')->count(),
            'events' => Event::count(),
            'tickets' => Ticket::count(),
            'paid_tickets' => Ticket::where('status', TicketStatus::Paid)->count(),
            'pending_tickets' => Ticket::where('status', TicketStatus::Pending)->count(),
            'seats_paid' => (int) Ticket::where('status', TicketStatus::Paid)->sum('quantity'),
            'revenue' => (float) $revenue,
        ];
    }
}
