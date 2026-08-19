<?php

namespace App\Listeners;

use App\Events\TicketStatusChanged;
use App\Services\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mirrors the realtime broadcast as a push, so a holder who does not have the
 * page open still learns their ticket was verified.
 */
class SendTicketStatusPush implements ShouldQueue
{
    public function __construct(private readonly PushSender $sender) {}

    public function handle(TicketStatusChanged $event): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        $event->ticket->loadMissing('event', 'pushSubscriptions');

        $this->sender->ticketStatusChanged($event->ticket);
    }
}
