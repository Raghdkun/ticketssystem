<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * How many events one page of the listing carries.
     *
     * Deliberately generous rather than paginated: the listing is grouped by
     * month, and a "next page" that cuts a month in half reads as a bug. With
     * a venue filter and a search box in front of it, sixty is a long way past
     * anything a visitor scrolls through.
     */
    private const LIMIT = 60;

    /**
     * Public landing page: what is on, and how to find a ticket you already
     * booked. Only events that are published, still open, and belong to an
     * active venue are listed.
     */
    public function __invoke(Request $request): Response
    {
        $venue = $request->string('venue')->trim()->value();
        $search = $request->string('q')->trim()->limit(80, '')->value();

        $matching = $this->openEvents()
            ->when($venue !== '', fn (Builder $query) => $query->whereHas(
                'place',
                fn (Builder $place) => $place->where('slug', $venue)
            ))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('title_ar', 'ilike', '%'.$search.'%')
                    ->orWhere('title_en', 'ilike', '%'.$search.'%')
            ));

        // Counted before the limit, so "showing 60 of 214" is honest.
        $total = (clone $matching)->count();

        $events = $matching
            ->with('place:id,slug,name_ar,name_en')
            ->orderBy('starts_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Event $event) => [
                'slug' => $event->slug,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'cover' => $event->cover_variants['thumb'] ?? null,
                'is_free' => $event->isFree(),
                'price' => (float) $event->price,
                'currency' => $event->currency,
                'seats_remaining' => $event->seatsRemaining(),
                'place_slug' => $event->place->slug,
                'place_name_ar' => $event->place->name_ar,
                'place_name_en' => $event->place->name_en,
            ])
            ->all();

        return Inertia::render('welcome', [
            'events' => $events,
            'venues' => $this->venuesWithEvents(),
            'filters' => ['venue' => $venue, 'q' => $search],
            'total' => $total,
            'limit' => self::LIMIT,
        ]);
    }

    /**
     * @return Builder<Event>
     */
    private function openEvents(): Builder
    {
        return $this->openScope(Event::query())
            ->whereHas('place', fn (Builder $query) => $query->where('is_active', true));
    }

    /**
     * Published, and still taking bookings.
     *
     * A named method rather than a closure so the venue counts can reuse it
     * with the event builder's own scopes in scope.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function openScope(Builder $query): Builder
    {
        return $query
            ->published()
            ->where('appointments_close_at', '>', now());
    }

    /**
     * Venues that actually have something on, with a count each.
     *
     * A filter offering a venue with nothing behind it is a dead end, and the
     * count is what makes the filter worth using rather than guessing at.
     *
     * @return array<int, array{slug: string, name_ar: string, name_en: string, events: int}>
     */
    private function venuesWithEvents(): array
    {
        return Place::query()
            ->where('is_active', true)
            ->withCount(['events' => $this->openScope(...)])
            ->orderByDesc('events_count')
            ->get(['id', 'slug', 'name_ar', 'name_en'])
            // Filtered here rather than in a HAVING clause: Postgres will not
            // let one reference a subquery alias, and this list is a handful
            // of venues, not a page of results.
            ->filter(fn (Place $place) => $place->events_count > 0)
            ->values()
            ->map(fn (Place $place) => [
                'slug' => $place->slug,
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'events' => $place->events_count,
            ])
            ->all();
    }
}
