<?php

namespace Tests\Feature;

use App\Actions\VerifyTicket;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoorCheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->event = Event::factory()->for(Place::factory()->for($this->owner))->create();
    }

    private function ticket(int $quantity): Ticket
    {
        return Ticket::factory()->for($this->event)->create(['quantity' => $quantity]);
    }

    public function test_a_full_party_checks_in_completely(): void
    {
        $ticket = $this->ticket(4);

        app(VerifyTicket::class)->markPaid($ticket, $this->owner);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertSame(4, $ticket->arrived_quantity);
    }

    /**
     * The case that motivated this: five booked, three at the door.
     */
    public function test_only_part_of_a_party_can_check_in(): void
    {
        $ticket = $this->ticket(5);

        $this->actingAs($this->owner)
            ->post(route('tickets.verify.paid', $ticket), ['arrived' => 3])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertSame(3, $ticket->arrived_quantity);
        // The booking still records what was reserved.
        $this->assertSame(5, $ticket->quantity);
    }

    public function test_the_arrival_count_cannot_exceed_the_booking(): void
    {
        $ticket = $this->ticket(2);

        $this->actingAs($this->owner)
            ->post(route('tickets.verify.paid', $ticket), ['arrived' => 9])
            ->assertSessionHasErrors('arrived');

        $this->assertSame(0, $ticket->fresh()->arrived_quantity);
    }

    public function test_a_partial_check_in_can_be_corrected_upward(): void
    {
        $ticket = $this->ticket(5);
        $verifier = app(VerifyTicket::class);

        $verifier->markPaid($ticket, $this->owner, 2);
        $verifier->markPaid($ticket->fresh(), $this->owner, 4);

        $this->assertSame(4, $ticket->fresh()->arrived_quantity);
    }

    public function test_a_partial_check_in_is_recorded_in_the_audit_trail(): void
    {
        $ticket = $this->ticket(5);

        app(VerifyTicket::class)->markPaid($ticket, $this->owner, 3);

        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $ticket->id,
            'to_status' => TicketStatus::Paid->value,
            'note' => 'partial check-in 3/5',
        ]);
    }

    public function test_a_no_show_is_distinct_from_a_cancellation(): void
    {
        $noShow = $this->ticket(2);
        $cancelled = $this->ticket(2);
        $verifier = app(VerifyTicket::class);

        $verifier->markNoShow($noShow, $this->owner);
        $verifier->cancel($cancelled, $this->owner);

        $this->assertSame(TicketStatus::NoShow, $noShow->fresh()->status);
        $this->assertNotNull($noShow->fresh()->no_show_at);

        $this->assertSame(TicketStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertNull($cancelled->fresh()->no_show_at);
    }

    public function test_a_no_show_releases_its_seats(): void
    {
        $ticket = $this->ticket(3);
        $this->assertSame(3, $this->event->fresh()->seatsTaken());

        app(VerifyTicket::class)->markNoShow($ticket, $this->owner);

        $this->assertSame(0, $this->event->fresh()->seatsTaken());
    }

    public function test_a_stranger_cannot_mark_a_no_show(): void
    {
        $ticket = $this->ticket(2);

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.verify.no_show', $ticket))
            ->assertForbidden();

        $this->assertSame(TicketStatus::Pending, $ticket->fresh()->status);
    }

    public function test_checking_in_after_a_no_show_clears_it(): void
    {
        $ticket = $this->ticket(3);
        $verifier = app(VerifyTicket::class);

        $verifier->markNoShow($ticket, $this->owner);
        $verifier->markPaid($ticket->fresh(), $this->owner, 3);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertNull($ticket->no_show_at);
        $this->assertSame(3, $ticket->arrived_quantity);
    }
}
