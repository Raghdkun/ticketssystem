<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PlatformStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                'setup' => null,
                'stats' => null,
                'recent' => [],
                'upcoming' => [],
            ]);
        }

        return Inertia::render('dashboard', [
            'hasPlace' => true,
            'platform' => null,
            'place' => [
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                // Every event at a venue is priced in the same currency, so
                // the first one is representative for the summary figures.
                'currency' => $place->events()->value('currency') ?? 'SYP',
            ],
            'setup' => $this->setupSteps($place),
            'stats' => $this->stats($place),
            'recent' => $this->recentAppointments($place),
            'upcoming' => $this->upcomingEvents($place),
        ]);
    }

    /**
     * The first-run checklist, or null once the venue is properly up.
     *
     * A freshly invited owner used to land on a dashboard of zeroes with
     * nothing telling them what to do next -- the invitation sets their venue
     * up beautifully and then abandons them in an empty room.
     *
     * It disappears the moment something is published, which is the point at
     * which the venue is actually open for business. Inviting door staff is
     * listed but never gates that: plenty of venues are one person.
     *
     * @return array<string, bool>|null
     */
    private function setupSteps(Place $place): ?array
    {
        $published = $place->events()->where('status', EventStatus::Published)->exists();

        if ($published) {
            return null;
        }

        return [
            'location' => $place->locations()->exists(),
            'event' => $place->events()->exists(),
            'published' => false,
            'staff' => User::query()->where('door_staff_for', $place->id)->exists(),
        ];
    }

    /**
     * Money is decimal(10,2) at the column, so the collected and outstanding
     * figures are genuinely fractional; trend is null with no prior month.
     *
     * @return array{
     *     published_events: int, draft_events: int, pending: int, paid: int,
     *     seats_paid: int, awaiting_seats: int, no_show: int, attendance: int,
     *     collected_month: float, outstanding: float, trend: int|null,
     * }
     */
    private function stats(Place $place): array
    {
        $eventIds = $place->events()->pluck('id');
        $tickets = Ticket::whereIn('event_id', $eventIds);

        $money = $this->money($eventIds);
        $paid = (clone $tickets)->where('status', TicketStatus::Paid);
        $seatsPaid = (int) (clone $paid)->sum('quantity');
        $seatsArrived = (int) (clone $paid)->sum('arrived_quantity');

        return [
            'published_events' => $place->events()->where('status', EventStatus::Published)->count(),
            'draft_events' => $place->events()->where('status', EventStatus::Draft)->count(),
            'pending' => (clone $tickets)->where('status', TicketStatus::Pending)->count(),
            'paid' => (clone $paid)->count(),
            'seats_paid' => $seatsPaid,
            // What the owner should collect at the door today.
            'awaiting_seats' => (int) (clone $tickets)->where('status', TicketStatus::Pending)->sum('quantity'),
            'no_show' => (clone $tickets)->where('status', TicketStatus::NoShow)->count(),
            // Of the seats paid for, how many walked in.
            'attendance' => $seatsPaid > 0 ? (int) round($seatsArrived / $seatsPaid * 100) : 0,
            'collected_month' => $money['collected_month'],
            'outstanding' => $money['outstanding'],
            'trend' => $money['trend'],
        ];
    }

    /**
     * Money collected this month, against the month before it.
     *
     * The owner's real question is "how is this month going", which a running
     * total since launch cannot answer.
     *
     * @param  Collection<int, int>  $eventIds
     * @return array{collected_month: float, outstanding: float, trend: int|null}
     */
    private function money(Collection $eventIds): array
    {
        $collected = fn (CarbonImmutable $from, CarbonImmutable $to): float => (float) Ticket::query()
            ->join('events', 'events.id', '=', 'tickets.event_id')
            ->whereIn('tickets.event_id', $eventIds)
            ->where('tickets.status', TicketStatus::Paid)
            ->whereBetween('tickets.verified_at', [$from, $to])
            ->sum(DB::raw('tickets.quantity * events.price'));

        $now = now();
        $thisMonth = $collected($now->startOfMonth(), $now);
        $lastMonth = $collected(
            $now->subMonth()->startOfMonth(),
            $now->subMonth()->endOfMonth(),
        );

        $outstanding = (float) Ticket::query()
            ->join('events', 'events.id', '=', 'tickets.event_id')
            ->whereIn('tickets.event_id', $eventIds)
            ->where('tickets.status', TicketStatus::Pending)
            ->sum(DB::raw('tickets.quantity * events.price'));

        return [
            'collected_month' => $thisMonth,
            'outstanding' => $outstanding,
            // Null rather than 100% when there is nothing to compare against:
            // a fabricated trend is worse than no trend.
            'trend' => $lastMonth > 0
                ? (int) round(($thisMonth - $lastMonth) / $lastMonth * 100)
                : null,
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
            // Drafts are included: an unpublished event whose date is closing
            // in is exactly the thing the owner most needs nudging about.
            ->whereIn('status', [EventStatus::Published, EventStatus::Draft])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'is_draft' => $event->status === EventStatus::Draft,
                'total_quantity' => $event->total_quantity,
                'seats_taken' => $event->seatsTaken(),
            ])
            ->all();
    }
}
