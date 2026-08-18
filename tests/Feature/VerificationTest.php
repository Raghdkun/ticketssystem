<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $event = Event::factory()->for(Place::factory()->for($this->owner))->create();
        $this->ticket = Ticket::factory()->for($event)->create(['quantity' => 2]);
    }

    /**
     * The QR is rendered on the attendee's own phone, so possession of the
     * verification URL must never be sufficient to verify.
     */
    public function test_an_unauthenticated_holder_cannot_open_the_verification_page(): void
    {
        $this->get(route('tickets.verify', $this->ticket))
            ->assertRedirect(route('login'));
    }

    public function test_an_unauthenticated_holder_cannot_mark_their_own_ticket_paid(): void
    {
        $this->post(route('tickets.verify.paid', $this->ticket))
            ->assertRedirect(route('login'));

        $this->assertSame(TicketStatus::Pending, $this->ticket->fresh()->status);
    }

    public function test_a_different_owner_cannot_verify_someone_elses_ticket(): void
    {
        $stranger = User::factory()->create();
        Place::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->post(route('tickets.verify.paid', $this->ticket))
            ->assertForbidden();

        $this->assertSame(TicketStatus::Pending, $this->ticket->fresh()->status);
    }

    public function test_a_different_owner_cannot_view_the_verification_page(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('tickets.verify', $this->ticket))
            ->assertForbidden();
    }

    public function test_the_owner_can_mark_a_ticket_paid(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tickets.verify.paid', $this->ticket))
            ->assertRedirect();

        $ticket = $this->ticket->fresh();

        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertNotNull($ticket->verified_at);
        $this->assertSame($this->owner->id, $ticket->verified_by);
        // A paid ticket must no longer be able to expire.
        $this->assertNull($ticket->hold_expires_at);

        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $ticket->id,
            'from_status' => TicketStatus::Pending->value,
            'to_status' => TicketStatus::Paid->value,
            'actor_id' => $this->owner->id,
        ]);
    }

    public function test_marking_paid_twice_is_idempotent(): void
    {
        $this->actingAs($this->owner)->post(route('tickets.verify.paid', $this->ticket));
        $firstVerifiedAt = $this->ticket->fresh()->verified_at;

        $this->actingAs($this->owner)->post(route('tickets.verify.paid', $this->ticket));

        $this->assertEquals($firstVerifiedAt, $this->ticket->fresh()->verified_at);
        $this->assertSame(1, $this->ticket->statusLogs()->where('to_status', TicketStatus::Paid->value)->count());
    }

    public function test_the_owner_can_cancel_a_ticket_and_free_its_seats(): void
    {
        $event = $this->ticket->event;
        $this->assertSame(2, $event->seatsTaken());

        $this->actingAs($this->owner)
            ->post(route('tickets.verify.cancel', $this->ticket))
            ->assertRedirect();

        $this->assertSame(TicketStatus::Cancelled, $this->ticket->fresh()->status);
        $this->assertSame(0, $event->fresh()->seatsTaken());
    }

    public function test_the_owner_can_open_the_verification_page(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tickets.verify', $this->ticket))
            ->assertOk();
    }

    public function test_manual_lookup_only_returns_the_owners_own_tickets(): void
    {
        $stranger = User::factory()->create();
        $strangerEvent = Event::factory()->for(Place::factory()->for($stranger))->create();
        Ticket::factory()->for($strangerEvent)->create(['phone' => $this->ticket->phone]);

        $response = $this->actingAs($this->owner)
            ->get(route('owner.scan', ['phone' => $this->ticket->phone]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('owner/scan')->has('results', 1)
        );
    }
}
