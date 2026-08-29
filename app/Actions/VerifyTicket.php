<?php

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a ticket to a terminal status on behalf of an owner.
 *
 * Every transition is idempotent: re-scanning an already-verified ticket
 * reports the original verification rather than stamping it again.
 */
final class VerifyTicket
{
    /**
     * Check in some or all of a booking's seats.
     *
     * A party of five where three arrive is the normal case at a door, not an
     * edge case: the arrived count is recorded separately from the booked
     * quantity so the owner keeps both what was reserved and what was used.
     *
     * @param  int|null  $arrived  Seats through the door; null means all of them.
     */
    public function markPaid(Ticket $ticket, User $actor, ?int $arrived = null): Ticket
    {
        return $this->transition($ticket, TicketStatus::Paid, $actor, function (Ticket $ticket) use ($actor, $arrived) {
            $ticket->verified_at = now();
            $ticket->verified_by = $actor->id;
            $ticket->arrived_quantity = max(1, min($arrived ?? $ticket->quantity, $ticket->quantity));
            // A paid ticket no longer expires.
            $ticket->hold_expires_at = null;
            $ticket->no_show_at = null;
        }, note: $arrived !== null && $arrived < $ticket->quantity
            ? "partial check-in {$arrived}/{$ticket->quantity}"
            : null);
    }

    /**
     * Record that nobody arrived. Distinct from a cancellation, which the
     * holder asked for, and from an expiry, which the clock caused.
     */
    public function markNoShow(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, TicketStatus::NoShow, $actor, function (Ticket $ticket) {
            $ticket->no_show_at = now();
            $ticket->arrived_quantity = 0;
            $ticket->hold_expires_at = null;
        });
    }

    public function cancel(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, TicketStatus::Cancelled, $actor, function (Ticket $ticket) {
            $ticket->cancelled_at = now();
            $ticket->hold_expires_at = null;
        });
    }

    /**
     * The holder released their own seats.
     *
     * Same terminal status as a venue cancellation -- the seats go back either
     * way -- but there is no actor, because the person doing it has no account.
     * The note is what tells the two apart in the log and on the door sheet.
     */
    public function releaseByHolder(Ticket $ticket): Ticket
    {
        return $this->transition($ticket, TicketStatus::Cancelled, null, function (Ticket $ticket) {
            $ticket->cancelled_at = now();
            $ticket->hold_expires_at = null;
        }, note: 'released by holder');
    }

    /**
     * @param  callable(Ticket): void  $mutate
     */
    private function transition(
        Ticket $ticket,
        TicketStatus $to,
        ?User $actor,
        callable $mutate,
        ?string $note = null,
    ): Ticket {
        return DB::transaction(function () use ($ticket, $to, $actor, $mutate, $note) {
            /** @var Ticket $locked */
            $locked = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-checking in with a different arrival count is a correction,
            // not a duplicate, so only an identical no-op is short-circuited.
            $isCorrection = $to === TicketStatus::Paid && $note !== null;

            if ($locked->status === $to && ! $isCorrection) {
                return $locked;
            }

            $from = $locked->status;

            $locked->status = $to;
            $mutate($locked);
            $locked->save();

            $locked->statusLogs()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_id' => $actor?->id,
                'note' => $note,
            ]);

            TicketStatusChanged::dispatch($locked);

            return $locked;
        });
    }
}
