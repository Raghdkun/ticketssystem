<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;

/**
 * The numbers an owner actually needs after an event.
 *
 * Money is derived from the event price at read time rather than stored per
 * ticket, so a price change is reflected consistently; if per-ticket pricing
 * is ever introduced this is the single place that has to change.
 */
final class EventReport
{
    /**
     * @return array<string, mixed>
     */
    public function for(Event $event): array
    {
        $tickets = $event->tickets()->get();
        $price = (float) $event->price;

        $byStatus = [];
        foreach (TicketStatus::cases() as $status) {
            $matching = $tickets->where('status', $status);
            $byStatus[$status->value] = [
                'bookings' => $matching->count(),
                'seats' => (int) $matching->sum('quantity'),
            ];
        }

        $paid = $tickets->where('status', TicketStatus::Paid);
        $pending = $tickets->where('status', TicketStatus::Pending);

        $seatsBooked = (int) $tickets->whereIn('status', [TicketStatus::Paid, TicketStatus::Pending])->sum('quantity');
        $seatsArrived = (int) $paid->sum('arrived_quantity');
        $seatsPaid = (int) $paid->sum('quantity');

        // Someone who paid for five and brought three still paid for five.
        $collected = $price * $seatsPaid;
        $outstanding = $price * (int) $pending->sum('quantity');

        $unattended = $tickets->whereIn('status', TicketStatus::unattended());

        return [
            'by_status' => $byStatus,
            'totals' => [
                'bookings' => $tickets->count(),
                'seats_capacity' => $event->total_quantity,
                'seats_booked' => $seatsBooked,
                'seats_paid' => $seatsPaid,
                'seats_arrived' => $seatsArrived,
                'seats_remaining' => $event->seatsRemaining(),
            ],
            'money' => [
                'currency' => $event->currency,
                'price' => $price,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'potential' => $price * $event->total_quantity,
            ],
            'rates' => [
                // Of the seats that were paid for, how many walked in.
                'attendance' => $seatsPaid > 0 ? round($seatsArrived / $seatsPaid * 100) : 0,
                'fill' => $event->total_quantity > 0 ? round($seatsBooked / $event->total_quantity * 100) : 0,
                'no_show_bookings' => $unattended->count(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(Event $event): array
    {
        return $event->tickets()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'reference' => strtoupper(substr($ticket->public_token, 0, 8)),
                'name' => $ticket->full_name,
                'phone' => $ticket->phone,
                'seats' => $ticket->quantity,
                'arrived' => $ticket->arrived_quantity,
                'status' => $ticket->status->value,
                'booked_at' => $ticket->created_at?->toDateTimeString(),
                'verified_at' => $ticket->verified_at?->toDateTimeString(),
            ])
            ->all();
    }
}
