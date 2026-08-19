<?php

namespace App\Http\Controllers\Owner;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DoorSheetController extends Controller
{
    /**
     * A printable attendee list for the door.
     *
     * Scanning depends on a working camera and a live connection; a venue
     * entrance reliably has neither. This is the paper fallback, deliberately
     * plain and sorted by name so a person can find a booking by eye.
     */
    public function __invoke(Request $request, Event $event): Response
    {
        $this->authorize('verifyTickets', $event);

        $tickets = $event->tickets()
            ->whereIn('status', [TicketStatus::Pending, TicketStatus::Paid])
            ->orderBy('full_name')
            ->get();

        return Inertia::render('owner/door-sheet', [
            'event' => [
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'total_quantity' => $event->total_quantity,
                'price' => (float) $event->price,
                'currency' => $event->currency,
                'is_free' => $event->isFree(),
            ],
            'place' => [
                'name_ar' => $event->place->name_ar,
                'name_en' => $event->place->name_en,
            ],
            'summary' => [
                'bookings' => $tickets->count(),
                'seats' => (int) $tickets->sum('quantity'),
                'paid_seats' => (int) $tickets->where('status', TicketStatus::Paid)->sum('quantity'),
                'outstanding_seats' => (int) $tickets->where('status', TicketStatus::Pending)->sum('quantity'),
            ],
            'rows' => $tickets->map(fn (Ticket $ticket) => [
                'reference' => strtoupper(substr($ticket->public_token, 0, 8)),
                'full_name' => $ticket->full_name,
                'phone' => $ticket->phone,
                'quantity' => $ticket->quantity,
                'arrived_quantity' => $ticket->arrived_quantity,
                'status' => $ticket->status->value,
                'amount_due' => $event->isFree()
                    ? 0.0
                    : ($ticket->status === TicketStatus::Paid ? 0.0 : (float) $event->price * $ticket->quantity),
            ])->values()->all(),
        ]);
    }
}
