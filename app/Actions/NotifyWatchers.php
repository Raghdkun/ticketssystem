<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\EventWatcher;
use App\Services\PushSender;

/**
 * Offers a freed seat to whoever is waiting for it.
 *
 * Called wherever inventory comes back -- a lapsed hold, a cancellation by the
 * venue, a holder releasing their own seats. It is deliberately cheap and
 * idempotent: it checks the event is actually bookable again, then works the
 * queue in the order people joined it.
 */
final class NotifyWatchers
{
    public function __construct(private readonly PushSender $push) {}

    /**
     * @return int Number of people told.
     */
    public function __invoke(Event $event): int
    {
        // Nothing to offer, or nothing anyone could do with it.
        if (! $event->isOpenForAppointments() || $event->seatsRemaining() < 1) {
            return 0;
        }

        $told = 0;

        // Only as many people as there are seats: telling forty people about
        // one returned seat is a race the other thirty-nine lose.
        $event->watchers()
            ->waiting()
            ->orderBy('id')
            ->limit($event->seatsRemaining())
            ->get()
            ->each(function (EventWatcher $watcher) use ($event, &$told) {
                // The event is already in hand; without this the sender
                // reloads it, and its venue, once per person in the queue.
                $watcher->setRelation('event', $event);

                $this->push->seatFreed($watcher);

                // Stamped whether or not a device was reachable. Somebody with
                // no push token is still on the list the owner can call, and
                // leaving them unstamped would re-offer the same seat forever.
                $watcher->forceFill(['notified_at' => now()])->save();

                $told++;
            });

        return $told;
    }
}
