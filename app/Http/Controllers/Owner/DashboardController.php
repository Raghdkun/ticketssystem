<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Services\PlatformStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $place = $user->places()->first();

        if ($place === null) {
            return Inertia::render('dashboard', [
                'hasPlace' => false,
                // A super admin has no venue of their own, so the owner
                // dashboard is meaningless to them; show the platform instead.
                'platform' => $user->isSuperAdmin()
                    ? app(PlatformStats::class)->all()
                    : null,
                'stats' => null,
                'recent' => [],
                'upcoming' => [],
            ]);
        }

        return Inertia::render('dashboard', [
            'hasPlace' => true,
            'platform' => null,
            'place' => ['name_ar' => $place->name_ar, 'name_en' => $place->name_en],
            'stats' => $this->stats($place),
            'recent' => $this->recentAppointments($place),
            'upcoming' => $this->upcomingEvents($place),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(Place $place): array
    {
        $eventIds = $place->events()->pluck('id');
        $tickets = Ticket::whereIn('event_id', $eventIds);

        return [
            'published_events' => $place->events()->where('status', EventStatus::Published)->count(),
            'draft_events' => $place->events()->where('status', EventStatus::Draft)->count(),
            'pending' => (clone $tickets)->where('status', TicketStatus::Pending)->count(),
            'paid' => (clone $tickets)->where('status', TicketStatus::Paid)->count(),
            'seats_paid' => (int) (clone $tickets)->where('status', TicketStatus::Paid)->sum('quantity'),
            // What the owner should collect at the door today.
            'awaiting_seats' => (int) (clone $tickets)->where('status', TicketStatus::Pending)->sum('quantity'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentAppointments(Place $place): array
    {
        return Ticket::query()
            ->whereIn('event_id', $place->events()->select('id'))
            ->with('event:id,title_ar,title_en')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'token' => $ticket->public_token,
                'full_name' => $ticket->full_name,
                'quantity' => $ticket->quantity,
                'status' => $ticket->status->value,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'event_title_ar' => $ticket->event->title_ar,
                'event_title_en' => $ticket->event->title_en,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingEvents(Place $place): array
    {
        return $place->events()
            ->published()
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'total_quantity' => $event->total_quantity,
                'seats_taken' => $event->seatsTaken(),
            ])
            ->all();
    }
}
