<?php

namespace App\Http\Controllers\Owner;

use App\Actions\VerifyTicket;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Support\TicketPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerificationController extends Controller
{
    public function __construct(private readonly VerifyTicket $verifier) {}

    /**
     * Landing page for a scanned QR code.
     *
     * The QR is rendered on the attendee's own phone, so possession of this URL
     * proves nothing. Access is therefore gated on being signed in AND owning
     * the event — which is what stops an attendee marking themselves paid.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        $ticket->load('event.place');
        $this->authorize('verifyTickets', $ticket->event);

        return Inertia::render('owner/verify', [
            'ticket' => TicketPresenter::forOwner($ticket),
        ]);
    }

    public function markPaid(Request $request, Ticket $ticket): RedirectResponse
    {
        $ticket->load('event.place');
        $this->authorize('verifyTickets', $ticket->event);

        $this->verifier->markPaid($ticket, $request->user());

        return back()->with('success', __('verify.marked_paid'));
    }

    public function cancel(Request $request, Ticket $ticket): RedirectResponse
    {
        $ticket->load('event.place');
        $this->authorize('verifyTickets', $ticket->event);

        $this->verifier->cancel($ticket, $request->user());

        return back()->with('success', __('verify.cancelled'));
    }

    /**
     * Scanner page, plus manual lookup by mobile number. Results are scoped to
     * the signed-in owner's own events so this cannot be used to enumerate
     * other venues' attendees.
     */
    public function scanner(Request $request): Response
    {
        $phone = $request->string('phone')->trim()->value();
        $results = [];

        if (strlen($phone) >= 6) {
            $results = Ticket::query()
                ->with('event')
                ->whereHas('event.place', fn ($query) => $query->where('user_id', $request->user()->id))
                ->where('phone', 'like', '%'.$phone.'%')
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (Ticket $ticket) => TicketPresenter::forOwner($ticket))
                ->all();
        }

        return Inertia::render('owner/scan', [
            'phone' => $phone,
            'results' => $results,
        ]);
    }
}
