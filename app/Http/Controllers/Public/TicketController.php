<?php

namespace App\Http\Controllers\Public;

use App\Actions\VerifyTicket;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Support\EventPresenter;
use App\Support\SocialMeta;
use App\Support\TicketPresenter;
use Illuminate\Http\RedirectResponse;
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

    /**
     * The holder releases their own seats.
     *
     * Authorised by possession of the token, like the page itself. Only a
     * live hold can be released: a paid ticket is the venue's business and a
     * spent one has nothing left to give back.
     *
     * A POST, never a GET -- a link that cancels a booking when something
     * prefetches it is not a link.
     */
    public function release(Ticket $ticket, VerifyTicket $verifier): RedirectResponse
    {
        if (! $ticket->isPending()) {
            return back()->with('error', __('tickets.release_too_late'));
        }

        $verifier->releaseByHolder($ticket);

        return back()->with('success', __('tickets.released'));
    }
}
