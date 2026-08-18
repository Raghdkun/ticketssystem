<?php

namespace App\Actions;

use App\Enums\TicketStatus;
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
    public function markPaid(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, TicketStatus::Paid, $actor, function (Ticket $ticket) use ($actor) {
            $ticket->verified_at = now();
            $ticket->verified_by = $actor->id;
            // A paid ticket no longer expires.
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
     * @param  callable(Ticket): void  $mutate
     */
    private function transition(Ticket $ticket, TicketStatus $to, User $actor, callable $mutate): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor, $mutate) {
            /** @var Ticket $locked */
            $locked = Ticket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === $to) {
                return $locked;
            }

            $from = $locked->status;

            $locked->status = $to;
            $mutate($locked);
            $locked->save();

            $locked->statusLogs()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_id' => $actor->id,
            ]);

            return $locked;
        });
    }
}
