<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Support\TicketPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketSearchController extends Controller
{
    /**
     * Search every ticket across all of the owner's events.
     *
     * The scanner's lookup is scoped to one event, which is no help when a
     * guest turns up at the wrong night or cannot remember which event they
     * booked. Results are always constrained to venues this user owns.
     */
    public function __invoke(Request $request): Response
    {
        $term = $request->string('q')->trim()->value();
        $results = [];

        if (mb_strlen($term) >= 3) {
            $results = Ticket::query()
                ->with('event')
                ->whereIn('event_id', $this->ownedEventIds($request))
                ->where(fn (Builder $query) => $query
                    ->where('full_name', 'ilike', '%'.$this->escapeLike($term).'%')
                    ->orWhere('phone', 'like', '%'.$this->escapeLike($term).'%')
                    ->orWhere('public_token', 'ilike', $this->escapeLike(mb_strtolower($term)).'%')
                )
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Ticket $ticket) => TicketPresenter::forOwner($ticket))
                ->all();
        }

        return Inertia::render('owner/search', [
            'q' => $term,
            'results' => $results,
        ]);
    }

    /**
     * @return Builder<Event>
     */
    private function ownedEventIds(Request $request): Builder
    {
        return Event::query()
            ->select('id')
            ->whereHas('place', fn (Builder $query) => $query->where('user_id', $request->user()->id));
    }

    /**
     * A bare % or _ would otherwise act as a wildcard and match everything.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
