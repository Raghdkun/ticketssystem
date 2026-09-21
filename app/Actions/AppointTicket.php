<?php

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Exceptions\AppointmentException;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Creates a pending appointment for an event.
 *
 * Seat availability is checked under a row lock on the event so that
 * simultaneous requests cannot both pass the check and oversell the event.
 */
final class AppointTicket
{
    /**
     * @param  array<int, int>  $acceptedRuleIds
     *
     * @throws AppointmentException
     */
    public function handle(
        Event $event,
        string $fullName,
        string $phone,
        int $quantity,
        array $acceptedRuleIds,
        string $locale = 'ar',
    ): Ticket {
        return DB::transaction(function () use ($event, $fullName, $phone, $quantity, $acceptedRuleIds, $locale) {
            // Serialise all appointments for this event. Every concurrent
            // request queues here, so the seat count below is never stale.
            /** @var Event $locked */
            $locked = Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpenForAppointments()) {
                throw AppointmentException::closed();
            }

            if ($quantity > $locked->max_per_appointment) {
                throw AppointmentException::tooMany();
            }

            $this->assertRulesAccepted($locked, $acceptedRuleIds);

            // An event with no capacity never sells out; every other one is
            // checked against what is left under the lock.
            if (! $locked->hasSeatsFor($quantity)) {
                throw AppointmentException::soldOut((int) $locked->seatsRemaining());
            }

            $ticket = new Ticket([
                'full_name' => $fullName,
                'phone' => $phone,
                'quantity' => $quantity,
            ]);

            // A free event that confirms on the spot skips the hold: there
            // is nothing to pay, so the ticket is good the moment it exists.
            // It is still unverified -- the door stamps that when they arrive.
            $confirmed = $locked->autoConfirms();

            $ticket->event_id = $locked->id;
            $ticket->public_token = Ticket::generateToken();
            $ticket->status = $confirmed ? TicketStatus::Paid : TicketStatus::Pending;
            $ticket->hold_expires_at = $confirmed ? null : now()->addHours($locked->hold_hours);
            $ticket->accepted_rules_at = now();
            $ticket->accepted_rule_ids = $acceptedRuleIds;
            $ticket->locale = $locale;
            $ticket->save();

            $ticket->statusLogs()->create([
                'from_status' => null,
                'to_status' => $ticket->status->value,
                'note' => $confirmed ? 'confirmed on booking (free event)' : 'appointed',
            ]);

            TicketStatusChanged::dispatch($ticket);

            return $ticket;
        });
    }

    /**
     * Every rule currently attached to the event must have been accepted.
     *
     * @param  array<int, int>  $acceptedRuleIds
     */
    private function assertRulesAccepted(Event $event, array $acceptedRuleIds): void
    {
        $required = $event->rules()->pluck('id')->all();

        if (array_diff($required, $acceptedRuleIds) !== []) {
            throw AppointmentException::rulesNotAccepted();
        }
    }
}
