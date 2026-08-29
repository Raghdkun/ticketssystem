<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Support\EventPresenter;
use App\Support\SocialMeta;
use App\Support\TicketPresenter;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * The public ticket page. Access is granted by possession of the token in
     * the URL, so nothing here may leak data about any other ticket.
     */
    public function show(Ticket $ticket): Response
    {
        $ticket->load(['event.place', 'event.perks', 'event.promoVideo']);

        return Inertia::render('public/ticket', [
            'ticket' => TicketPresenter::forPublicPage($ticket),
            'event' => EventPresenter::forTicket($ticket),
            'place' => EventPresenter::place($ticket->event->place),
            // A ticket page is noindex, but noindex does not stop a preview
            // unfurling, and holders do share the link with whoever is coming
            // with them. This carries the event and venue only -- never the
            // holder's name or number, which are the reason for the noindex.
            'og' => SocialMeta::forEvent($ticket->event, $ticket->event->place),
            'siblings' => EventPresenter::siblingEvents($ticket->event->place, $ticket->event),
        ]);
    }
}
