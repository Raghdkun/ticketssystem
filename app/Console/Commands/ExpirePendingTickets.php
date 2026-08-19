<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingTickets extends Command
{
    protected $signature = 'tickets:expire';

    protected $description = 'Release seats held by pending tickets whose hold window has lapsed';

    public function handle(): int
    {
        $expired = 0;

        // Chunk by id so a large backlog cannot exhaust memory, and so each
        // batch commits independently rather than holding one long transaction.
        Ticket::query()
            ->lapsed()
            ->chunkById(200, function ($tickets) use (&$expired) {
                foreach ($tickets as $ticket) {
                    DB::transaction(function () use ($ticket, &$expired) {
                        /** @var Ticket $locked */
                        $locked = Ticket::query()
                            ->whereKey($ticket->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        // It may have been paid between the query and the lock.
                        if ($locked->status !== TicketStatus::Pending) {
                            return;
                        }

                        $locked->status = TicketStatus::Expired;
                        $locked->save();

                        $locked->statusLogs()->create([
                            'from_status' => TicketStatus::Pending->value,
                            'to_status' => TicketStatus::Expired->value,
                            'note' => 'hold lapsed',
                        ]);

                        TicketStatusChanged::dispatch($locked);

                        $expired++;
                    });
                }
            });

        $this->info("Expired {$expired} ticket(s).");

        return self::SUCCESS;
    }
}
