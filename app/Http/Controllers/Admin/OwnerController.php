<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/owners', [
            'stats' => $this->platformStats(),
            'owners' => $this->owners(),
        ]);
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403, 'Super admins cannot be banned.');

        $user->banned_at = now();
        $user->save();

        return back()->with('success', __('admin.banned'));
    }

    public function unban(User $user): RedirectResponse
    {
        $user->banned_at = null;
        $user->save();

        return back()->with('success', __('admin.unbanned'));
    }

    /**
     * Platform-wide figures. Seat counts come from tickets that still hold
     * inventory, matching what the public pages report.
     *
     * @return array<string, int|float>
     */
    private function platformStats(): array
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function owners(): array
    {
        return User::query()
            ->where('role', UserRole::Owner)
            ->withCount('places')
            ->with('places:id,user_id,name_ar,name_en,slug')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'banned' => $user->isBanned(),
                'banned_at' => $user->banned_at?->toIso8601String(),
                'places' => $user->places->map->only(['name_ar', 'name_en', 'slug'])->all(),
                'events_count' => Event::whereIn('place_id', $user->places->pluck('id'))->count(),
                'tickets_count' => Ticket::whereIn(
                    'event_id',
                    Event::whereIn('place_id', $user->places->pluck('id'))->select('id')
                )->count(),
            ])
            ->all();
    }
}
