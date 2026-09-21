<?php

namespace App\Http\Controllers\Public;

use App\Actions\AppointTicket;
use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Exceptions\AppointmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AppointTicketRequest;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointTicket $appoint) {}

    public function store(AppointTicketRequest $request, Place $place, Event $event): RedirectResponse
    {
        if ($event->place_id !== $place->id || $event->status !== EventStatus::Published) {
            throw new NotFoundHttpException;
        }

        try {
            $ticket = $this->appoint->handle(
                event: $event,
                fullName: $request->string('full_name')->value(),
                phone: $request->normalisedPhone(),
                quantity: $request->integer('quantity'),
                acceptedRuleIds: $request->acceptedRuleIds(),
                locale: app()->getLocale(),
            );
        } catch (AppointmentException $e) {
            return back()->withInput()->withErrors(['quantity' => __($e->getMessage())]);
        }

        // A free event that confirms on booking has nothing left to pay, so
        // "show it at the venue to complete payment" would be wrong twice.
        return to_route('tickets.show', $ticket)->with('success', $ticket->status === TicketStatus::Paid
            ? __('tickets.confirmed')
            : __('tickets.appointed'));
    }
}
