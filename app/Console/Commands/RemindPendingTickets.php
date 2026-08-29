<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\PushSender;
use Illuminate\Console\Command;

/**
 * Nudges holders whose seats are about to be released.
 *
 * Almost nobody lets a hold lapse on purpose -- they meant to drop by the
 * venue and the day got away from them. Sending one reminder before the seats
 * go back turns lapsed holds into paid ones, which is inventory the venue is
 * currently losing.
 */
class RemindPendingTickets extends Command
{
    protected $signature = 'tickets:remind {--hours=3 : How long before expiry to send the nudge}';

    protected $description = 'Remind holders whose pending hold is about to lapse';

    public function handle(PushSender $sender): int
    {
        if (! $sender->isConfigured()) {
            $this->comment('Push is not configured; nothing to send.');

            return self::SUCCESS;
        }

        $hours = max(1, (int) $this->option('hours'));
        $sent = 0;

        Ticket::query()
            ->where('status', TicketStatus::Pending)
            ->whereNull('reminder_sent_at')
            // Already lapsed is the expiry command's business, not ours: a
            // "pay by 4pm" push that arrives at 5pm is worse than silence.
            ->where('hold_expires_at', '>', now())
            ->where('hold_expires_at', '<=', now()->addHours($hours))
            ->whereHas('pushSubscriptions')
            ->with(['event', 'pushSubscriptions'])
            ->chunkById(200, function ($tickets) use ($sender, &$sent) {
                foreach ($tickets as $ticket) {
                    $sender->holdReminder($ticket);

                    // Stamped whether or not a device accepted it. The point
                    // is one nudge per hold, not one successful delivery --
                    // retrying every minute against a dead phone is spam.
                    $ticket->forceFill(['reminder_sent_at' => now()])->save();

                    $sent++;
                }
            });

        $this->info("Reminded {$sent} holder(s).");

        return self::SUCCESS;
    }
}
