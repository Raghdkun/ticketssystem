<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\LocationRequest;
use App\Models\Location;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The places an owner's events actually happen.
 *
 * A venue is not always one address -- a main hall, a rooftop and a garden are
 * three locations under one venue -- so an event picks one rather than
 * inheriting a single pin from the place.
 */
class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $place = $this->place($request);

        if ($place === null) {
            return Inertia::render('owner/locations', ['locations' => [], 'hasPlace' => false]);
        }

        return Inertia::render('owner/locations', [
            'hasPlace' => true,
            'locations' => $place->locations()->with('images')->get()
                ->map(fn (Location $location) => $this->payload($location))->all(),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null, 404);
        $this->authorize('update', $place);

        $location = $place->locations()->create([
            ...$request->validated(),
            'sort' => (int) $place->locations()->max('sort') + 1,
        ]);

        $this->settlePrimary($location);

        return back()->with('success', __('ui.location.saved'));
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $location->update($request->validated());
        $this->settlePrimary($location);

        return back()->with('success', __('ui.location.saved'));
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        // Events keep working: the foreign key nulls out and they fall back to
        // the venue's primary location.
        $wasPrimary = $location->is_primary;
        $place = $location->place;

        $location->delete();

        // Never leave a venue with locations but no primary, or events with no
        // location would resolve to nothing.
        if ($wasPrimary) {
            $place->locations()->orderBy('sort')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', __('ui.location.deleted'));
    }

    /**
     * Exactly one primary per venue.
     */
    private function settlePrimary(Location $location): void
    {
        if ($location->is_primary) {
            Location::query()
                ->where('place_id', $location->place_id)
                ->whereKeyNot($location->getKey())
                ->update(['is_primary' => false]);

            return;
        }

        // Nothing is flagged: the first location becomes primary by default,
        // so a venue is never left without one.
        $hasPrimary = Location::query()
            ->where('place_id', $location->place_id)
            ->where('is_primary', true)
            ->exists();

        if (! $hasPrimary) {
            $location->update(['is_primary' => true]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Location $location): array
    {
        return [
            'id' => $location->id,
            'name_ar' => $location->name_ar,
            'name_en' => $location->name_en,
            'latitude' => $location->latitude === null ? null : (float) $location->latitude,
            'longitude' => $location->longitude === null ? null : (float) $location->longitude,
            'address_ar' => $location->address_ar,
            'address_en' => $location->address_en,
            'landmark_ar' => $location->landmark_ar,
            'landmark_en' => $location->landmark_en,
            'is_primary' => $location->is_primary,
            'images' => $location->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => '/storage/'.$image->path,
            ])->all(),
        ];
    }

    private function place(Request $request): ?Place
    {
        return $request->user()?->places()->first();
    }
}
