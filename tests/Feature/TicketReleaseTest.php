<?php

namespace Tests\Feature;

use App\Actions\VerifyTicket;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A holder giving their own seats back.
 *
 * Only an owner could cancel before this, so somebody who knew they could not
 * come had no way to release the seat -- it simply sat held until it expired.
 */
class TicketReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_holder_can_release_a_live_hold(): void
    {
        $event = Event::factory()->create(['total_quantity' => 10]);
        $ticket = Ticket::factory()->for($event)->create(['quantity' => 3]);

        $this->post("/t/{$ticket->public_token}/release")->assertRedirect();

        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
        $this->assertSame(10, $event->fresh()->seatsRemaining());
    }

    public function test_the_release_is_recorded_as_the_holders_own(): void
    {
        $ticket = Ticket::factory()->for(Event::factory())->create();

        $this->post("/t/{$ticket->public_token}/release");

        // No actor, because the person doing it has no account; the note is
        // what tells this apart from a venue cancelling somebody.
        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $ticket->id,
            'to_status' => TicketStatus::Cancelled->value,
            'actor_id' => null,
            'note' => 'released by holder',
        ]);
    }

    public function test_a_paid_ticket_cannot_be_released(): void
    {
        $ticket = Ticket::factory()->paid()->for(Event::factory())->create();

        $this->post("/t/{$ticket->public_token}/release")->assertRedirect();

        $this->assertSame(TicketStatus::Paid, $ticket->fresh()->status);
    }

    public function test_an_already_cancelled_ticket_is_left_alone(): void
    {
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->for($event)->create();
        $ticket->forceFill([
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        $this->post("/t/{$ticket->public_token}/release")->assertRedirect();

        // Nothing happened: no second cancellation, no second log line.
        $this->assertSame(0, $ticket->statusLogs()->count());
    }

    public function test_it_is_a_post_and_never_a_get(): void
    {
        $ticket = Ticket::factory()->for(Event::factory())->create();

        // A link that cancels a booking when something prefetches it is not
        // a link.
        $this->get("/t/{$ticket->public_token}/release")->assertMethodNotAllowed();

        $this->assertSame(TicketStatus::Pending, $ticket->fresh()->status);
    }

    public function test_one_token_cannot_release_another_booking(): void
    {
        $event = Event::factory()->create();
        $mine = Ticket::factory()->for($event)->create();
        $theirs = Ticket::factory()->for($event)->create();

        $this->post("/t/{$mine->public_token}/release");

        $this->assertSame(TicketStatus::Pending, $theirs->fresh()->status);
    }

    public function test_a_released_hold_frees_the_seats_for_someone_else(): void
    {
        $event = Event::factory()->create([
            'total_quantity' => 4,
            'appointments_close_at' => now()->addWeek(),
        ]);
        $ticket = Ticket::factory()->for($event)->create(['quantity' => 4]);

        $this->assertSame(0, $event->fresh()->seatsRemaining());

        $this->post("/t/{$ticket->public_token}/release");

        $this->assertSame(4, $event->fresh()->seatsRemaining());
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->post('/t/'.str_repeat('a', 32).'/release')->assertNotFound();
    }

    public function test_a_no_show_cannot_be_released(): void
    {
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->paid()->for($event)->create();

        app(VerifyTicket::class)->markNoShow($ticket, User::factory()->create());

        $this->post("/t/{$ticket->public_token}/release")->assertRedirect();

        $this->assertSame(TicketStatus::NoShow, $ticket->fresh()->status);
    }
}
