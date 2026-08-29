<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PlaceRequest;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The venue an owner manages.
 *
 * Places are provisioned by a super admin, but their details are the owner's
 * to maintain. Where events physically happen lives on locations, which a
 * venue can have several of -- see LocationController.
 */
class PlaceController extends Controller
{
    public function edit(Request $request): Response
    {
        $place = $this->place($request);

        if ($place === null) {
            return Inertia::render('owner/place', ['place' => null]);
        }

        $this->authorize('update', $place);

        return Inertia::render('owner/place', [
            'place' => [
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'whatsapp_number' => $place->whatsapp_number,
            ],
        ]);
    }

    public function update(PlaceRequest $request): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null, 404);

        $this->authorize('update', $place);

        $place->update($request->validated());

        return back()->with('success', __('ui.owner.place_saved'));
    }

    private function place(Request $request): ?Place
    {
        return $request->user()?->places()->first();
    }
}
