<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_lapsed_holds_and_returns_their_seats(): void
    {
        $event = Event::factory()->create(['total_quantity' => 10]);
        $lapsed = Ticket::factory()->lapsed()->for($event)->create(['quantity' => 4]);

        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertSame(TicketStatus::Expired, $lapsed->fresh()->status);
        $this->assertSame(10, $event->fresh()->seatsRemaining());

        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $lapsed->id,
            'from_status' => TicketStatus::Pending->value,
            'to_status' => TicketStatus::Expired->value,
        ]);
    }

    public function test_it_leaves_live_holds_alone(): void
    {
        $event = Event::factory()->create(['total_quantity' => 10]);
        $live = Ticket::factory()->for($event)->create(['quantity' => 2]);

        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertSame(TicketStatus::Pending, $live->fresh()->status);
        $this->assertSame(2, $event->fresh()->seatsTaken());
    }

    public function test_it_never_expires_a_paid_ticket(): void
    {
        $event = Event::factory()->create();
        // A paid ticket with a stale hold timestamp must still be left alone.
        $paid = Ticket::factory()->paid()->for($event)->create(['hold_expires_at' => now()->subDay()]);

        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertSame(TicketStatus::Paid, $paid->fresh()->status);
    }

    public function test_expired_seats_become_available_to_a_new_appointment(): void
    {
        $event = Event::factory()->create(['total_quantity' => 2]);
        Ticket::factory()->lapsed()->for($event)->create(['quantity' => 2]);

        $this->artisan('tickets:expire');

        $response = $this->post(route('events.appoint', [$event->place, $event]), [
            'full_name' => 'Nour Khoury',
            'phone' => '0991234567',
            'quantity' => 2,
            'accepted_rule_ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, $event->fresh()->seatsTaken());
    }
}
