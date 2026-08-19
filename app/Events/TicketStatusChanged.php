<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    /**
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            // Keyed by the ticket's unguessable token. Holders are anonymous,
            // so a private channel could not authorise them; the token is
            // already the secret that grants access to the page itself.
            new Channel("ticket.{$this->ticket->public_token}"),

            // The owner's live appointment feed, authorised by session.
            new PrivateChannel("place.{$this->ticket->event->place_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    /**
     * Deliberately minimal: this payload reaches an unauthenticated public
     * channel, so it carries status only and never personal data.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'token' => $this->ticket->public_token,
            'status' => $this->ticket->status->value,
            'verified_at' => $this->ticket->verified_at?->toIso8601String(),
        ];
    }
}
