<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Public landing page: what is on, and how to find a ticket you already
     * booked. Only events that are published, still open, and belong to an
     * active venue are listed.
     */
    public function __invoke(): Response
    {
        $events = Event::query()
            ->published()
            ->where('appointments_close_at', '>', now())
            ->whereHas('place', fn ($query) => $query->where('is_active', true))
            ->with('place:id,slug,name_ar,name_en')
            ->orderBy('starts_at')
            ->limit(24)
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

        return Inertia::render('welcome', ['events' => $events]);
    }
}
