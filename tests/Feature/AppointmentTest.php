<?php

namespace Tests\Feature;

use App\Actions\AppointTicket;
use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Exceptions\AppointmentException;
use App\Models\Event;
use App\Models\EventRule;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): Event
    {
        return Event::factory()->create($attributes);
    }

    private function appoint(Event $event, int $quantity = 1, ?array $ruleIds = null): Ticket
    {
        return app(AppointTicket::class)->handle(
            event: $event,
            fullName: 'Layla Haddad',
            phone: '+963991234567',
            quantity: $quantity,
            acceptedRuleIds: $ruleIds ?? $event->rules()->pluck('id')->all(),
        );
    }

    public function test_it_creates_a_pending_ticket_holding_seats(): void
    {
        $event = $this->event(['total_quantity' => 10, 'hold_hours' => 6]);

        $ticket = $this->appoint($event, 3);

        $this->assertSame(TicketStatus::Pending, $ticket->status);
        $this->assertSame(3, $ticket->quantity);
        $this->assertSame(32, strlen($ticket->public_token));
        $this->assertTrue($ticket->hold_expires_at->between(now()->addHours(5), now()->addHours(7)));
        $this->assertSame(3, $event->fresh()->seatsTaken());

        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => TicketStatus::Pending->value,
        ]);
    }

    public function test_it_rejects_an_appointment_beyond_remaining_seats(): void
    {
        $event = $this->event(['total_quantity' => 5]);
        $this->appoint($event, 4);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('tickets.errors.not_enough_seats');

        $this->appoint($event, 2);
    }

    public function test_a_lapsed_hold_releases_its_seats_for_reuse(): void
    {
        $event = $this->event(['total_quantity' => 2]);
        Ticket::factory()->lapsed()->for($event)->create(['quantity' => 2]);

        $this->assertSame(0, $event->fresh()->seatsTaken());

        $ticket = $this->appoint($event, 2);

        $this->assertSame(TicketStatus::Pending, $ticket->status);
    }

    public function test_it_requires_every_rule_to_be_accepted(): void
    {
        $event = $this->event();
        $rules = EventRule::factory()->count(3)->for($event)->create();

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('tickets.errors.rules_not_accepted');

        // Accept only two of the three rules.
        $this->appoint($event, 1, $rules->take(2)->pluck('id')->all());
    }

    public function test_it_snapshots_the_accepted_rules(): void
    {
        $event = $this->event();
        $rules = EventRule::factory()->count(2)->for($event)->create();

        $ticket = $this->appoint($event, 1);

        $this->assertEqualsCanonicalizing($rules->pluck('id')->all(), $ticket->accepted_rule_ids);
    }

    public function test_it_rejects_appointments_after_the_closing_time(): void
    {
        $event = $this->event(['appointments_close_at' => now()->subMinute()]);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('tickets.errors.closed');

        $this->appoint($event);
    }

    public function test_it_rejects_appointments_for_an_unpublished_event(): void
    {
        $event = $this->event(['status' => EventStatus::Draft]);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('tickets.errors.closed');

        $this->appoint($event);
    }

    public function test_it_rejects_more_people_than_the_per_appointment_cap(): void
    {
        $event = $this->event(['total_quantity' => 100, 'max_per_appointment' => 4]);

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('tickets.errors.too_many');

        $this->appoint($event, 5);
    }

    public function test_the_public_endpoint_creates_a_ticket_and_redirects_to_it(): void
    {
        $event = $this->event(['total_quantity' => 10]);
        $rule = EventRule::factory()->for($event)->create();

        $response = $this->post(route('events.appoint', [$event->place, $event]), [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
            'quantity' => 2,
            'accepted_rule_ids' => [$rule->id],
        ]);

        $ticket = Ticket::sole();

        $response->assertRedirect(route('tickets.show', $ticket));
        // The number is normalised to E.164 regardless of how it was typed.
        $this->assertSame('+963991234567', $ticket->phone);
    }

    public function test_the_public_endpoint_rejects_unaccepted_rules(): void
    {
        $event = $this->event();
        EventRule::factory()->for($event)->create();

        $this->post(route('events.appoint', [$event->place, $event]), [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
            'quantity' => 1,
            'accepted_rule_ids' => [],
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_draft_event_is_not_publicly_reachable(): void
    {
        $event = $this->event(['status' => EventStatus::Draft]);

        $this->get(route('events.show', [$event->place, $event]))->assertNotFound();
    }

    public function test_a_published_event_page_renders(): void
    {
        $event = $this->event();

        $this->get(route('events.show', [$event->place, $event]))->assertOk();
    }
}
