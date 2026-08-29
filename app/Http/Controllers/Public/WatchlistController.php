<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Joining the queue for a sold-out event.
 *
 * A sold-out event is not an ending here: holds lapse and people cancel, so
 * seats come back regularly. Without this the visitor has nowhere to go and
 * the venue never learns there was demand it could not meet.
 */
class WatchlistController extends Controller
{
    public function store(Request $request, Place $place, Event $event): RedirectResponse
    {
        if ($event->place_id !== $place->id || $event->status !== EventStatus::Published) {
            throw new NotFoundHttpException;
        }

        if (! $event->isOpenForAppointments()) {
            return back()->with('error', __('tickets.watch_closed'));
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', (new Phone)->country(['SY'])->mobile()],
        ]);

        $phone = (new PhoneNumber($validated['phone'], 'SY'))->formatE164();

        // Joining twice is a double tap, not a second claim: the same number
        // keeps its original place in the queue rather than losing it.
        $event->watchers()->firstOrCreate(
            ['phone' => $phone],
            [
                'full_name' => $validated['full_name'],
                'locale' => app()->getLocale(),
            ],
        );

        return back()->with('success', __('tickets.watching'));
    }
}
