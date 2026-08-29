<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Location;
use App\Models\Place;
use App\Support\SocialMeta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A venue's own page.
 *
 * Until now `/{venue}` was a dead URL: only `/{venue}/{event}` was routed, so
 * trimming a shared link, or following a venue name from anywhere, hit a 404.
 * This is the page a venue can hand out for everything it runs.
 */
class PlaceController extends Controller
{
    public function show(Place $place): Response
    {
        abort_unless($place->is_active, 404);

        $events = $place->events()
            ->published()
            ->with('location')
            ->orderBy('starts_at')
            ->get();

        [$past, $upcoming] = $events->partition(
            fn (Event $event) => $event->starts_at->isPast()
        );

        return Inertia::render('public/place', [
            'place' => [
                'slug' => $place->slug,
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'logo' => $place->logo_path,
                'whatsapp_number' => $place->whatsapp_number,
            ],
            'locations' => $place->locations()->with('images')->get()
                ->map(fn (Location $location) => $location->forPublic())
                ->filter()
                ->values()
                ->all(),
            'upcoming' => $upcoming->map(fn (Event $event) => $this->card($event))->values()->all(),
            // Newest first: what just happened is more interesting than what
            // happened a year ago.
            'past' => $past->sortByDesc('starts_at')->take(12)
                ->map(fn (Event $event) => $this->card($event))->values()->all(),
            'og' => SocialMeta::forPlace($place),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'title_ar' => $event->title_ar,
            'title_en' => $event->title_en,
            'starts_at' => $event->starts_at->toIso8601String(),
            'cover' => $event->cover_variants['thumb'] ?? null,
            'price' => (float) $event->price,
            'currency' => $event->currency,
            'seats_remaining' => $event->seatsRemaining(),
            'is_open' => $event->isOpenForAppointments(),
            'location' => $event->location?->name(),
        ];
    }
}
