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
     * The events this account may search, whoever they are.
     *
     * Scoped by the venue they work at rather than the venue they own, so a
     * door hand sees their own venue's tickets -- and, just as importantly,
     * only those.
     *
     * @return Builder<Event>
     */
    private function ownedEventIds(Request $request): Builder
    {
        $place = $request->user()?->workingPlace();

        if ($place === null) {
            // No venue means no events, not every event.
            return Event::query()->select('id')->whereRaw('1 = 0');
        }

        return Event::query()->select('id')->where('place_id', $place->id);
    }

    /**
     * A bare % or _ would otherwise act as a wildcard and match everything.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
