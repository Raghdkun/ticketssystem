<?php

namespace App\Listeners;

use App\Actions\NotifyWatchers;
use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When a booking stops holding seats, offer them to the waiting list.
 *
 * Hung off the status event rather than each call site, because seats come
 * back three different ways -- a lapsed hold, a cancellation by the venue, a
 * holder releasing their own -- and all three end here.
 */
class OfferSeatToWatchers implements ShouldQueue
{
    public function __construct(private readonly NotifyWatchers $notify) {}

    public function handle(TicketStatusChanged $event): void
    {
        // A no-show frees nothing: the night has already happened.
        if (! in_array($event->ticket->status, [TicketStatus::Cancelled, TicketStatus::Expired], true)) {
            return;
        }

        $event->ticket->loadMissing('event.place');

        ($this->notify)($event->ticket->event);
    }
}
