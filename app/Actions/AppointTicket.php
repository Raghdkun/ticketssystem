<?php

namespace App\Actions;

use App\Enums\TicketStatus;
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

            $remaining = $locked->seatsRemaining();

            if ($quantity > $remaining) {
                throw AppointmentException::soldOut($remaining);
            }

            $ticket = new Ticket([
                'full_name' => $fullName,
                'phone' => $phone,
                'quantity' => $quantity,
            ]);

            $ticket->event_id = $locked->id;
            $ticket->public_token = Ticket::generateToken();
            $ticket->status = TicketStatus::Pending;
            $ticket->hold_expires_at = now()->addHours($locked->hold_hours);
            $ticket->accepted_rules_at = now();
            $ticket->accepted_rule_ids = $acceptedRuleIds;
            $ticket->locale = $locale;
            $ticket->save();

            $ticket->statusLogs()->create([
                'from_status' => null,
                'to_status' => TicketStatus::Pending->value,
                'note' => 'appointed',
            ]);

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
