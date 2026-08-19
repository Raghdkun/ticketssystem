<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use App\Support\EventPresenter;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventController extends Controller
{
    public function show(Place $place, Event $event): Response
    {
        $this->assertVisible($place, $event);

        return Inertia::render('public/event', [
            'event' => EventPresenter::forPublicPage($event),
            'place' => EventPresenter::place($place),
            'siblings' => EventPresenter::siblingEvents($place, $event),
        ]);
    }

    /**
     * Draft and archived events are not publicly addressable, and an event
     * must actually belong to the place in the URL.
     */
    private function assertVisible(Place $place, Event $event): void
    {
        if ($event->place_id !== $place->id || $event->status !== EventStatus::Published) {
            throw new NotFoundHttpException;
        }
    }
}
