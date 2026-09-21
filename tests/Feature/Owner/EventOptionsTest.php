<?php

namespace Tests\Feature\Owner;

use App\Actions\AppointTicket;
use App\Actions\NotifyWatchers;
use App\Actions\VerifyTicket;
use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Exceptions\AppointmentException;
use App\Models\Event;
use App\Models\EventWatcher;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The options an owner asked for on the event form, end to end.
 *
 * Unlimited capacity, confirm-on-booking for free events, unlisted events,
 * cover removal, repeat-on-save, the saved dialog payload, and deleting an
 * event -- which archives it instead when people hold tickets.
 */
class EventOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create();

        return $owner;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل',
            'title_en' => 'Concert',
            'price' => 25000,
            'currency' => 'SYP',
            'total_quantity' => 100,
            'max_per_appointment' => 4,
            'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            'status' => EventStatus::Published->value,
            ...$overrides,
        ];
    }

    private function appoint(Event $event, int $quantity = 1): Ticket
    {
        return app(AppointTicket::class)->handle(
            event: $event,
            fullName: 'Layla Haddad',
            phone: '+963991234567',
            quantity: $quantity,
            acceptedRuleIds: [],
        );
    }

    // ------------------------------------------------------------------
    // Unlimited capacity
    // ------------------------------------------------------------------

    public function test_ticking_unlimited_stores_no_capacity_and_ignores_the_count(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['unlimited' => '1', 'total_quantity' => 'nonsense']))
            ->assertSessionHasNoErrors();

        $event = Event::firstOrFail();

        $this->assertNull($event->total_quantity);
        $this->assertTrue($event->isUnlimited());
        $this->assertNull($event->seatsRemaining());
    }

    public function test_a_capacity_is_still_required_when_not_unlimited(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/events', $this->payload(['total_quantity' => '']))
            ->assertSessionHasErrors('total_quantity');
    }

    public function test_an_unlimited_event_never_sells_out(): void
    {
        $event = Event::factory()->unlimited()->create(['max_per_appointment' => 50]);

        foreach (range(1, 5) as $i) {
            $this->appoint($event, 50);
        }

        $this->assertSame(250, $event->fresh()->seatsTaken());
        $this->assertNull($event->fresh()->seatsRemaining());
        $this->assertTrue($event->fresh()->hasSeatsFor(1000));
    }

    public function test_a_limited_event_still_refuses_beyond_its_seats(): void
    {
        $event = Event::factory()->create(['total_quantity' => 2, 'max_per_appointment' => 5]);

        $this->expectException(AppointmentException::class);

        $this->appoint($event, 3);
    }

    public function test_the_public_page_sends_a_null_seat_count_for_an_unlimited_event(): void
    {
        $event = Event::factory()->unlimited()->create();

        $this->get(route('events.show', [$event->place, $event]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('event.seats_remaining', null)
                ->where('event.is_unlimited', true));

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('events.0.seats_remaining', null));
    }

    public function test_the_report_has_no_fill_rate_for_an_unlimited_event(): void
    {
        $event = Event::factory()->unlimited()->create();

        $this->actingAs($event->place->user)
            ->get("/owner/events/{$event->id}/report")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('report.totals.seats_capacity', null)
                ->where('report.rates.fill', null)
                ->where('report.money.potential', null));
    }

    public function test_lifting_the_capacity_offers_seats_to_the_whole_queue(): void
    {
        $event = Event::factory()->soldOut()->create();
        $this->appoint($event);
        EventWatcher::factory()->count(3)->for($event)->create();

        $event->update(['total_quantity' => null]);

        $told = app(NotifyWatchers::class)($event->fresh());

        $this->assertSame(3, $told);
    }

    // ------------------------------------------------------------------
    // Confirm on booking (free events)
    // ------------------------------------------------------------------

    public function test_a_free_event_that_confirms_on_booking_issues_a_paid_ticket_with_no_hold(): void
    {
        $event = Event::factory()->autoConfirming()->create();

        $ticket = $this->appoint($event, 2);

        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertNull($ticket->hold_expires_at);
        $this->assertNull($ticket->verified_at);
        $this->assertSame(2, $event->fresh()->seatsTaken());

        $this->assertDatabaseHas('ticket_status_logs', [
            'ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => TicketStatus::Paid->value,
            'note' => 'confirmed on booking (free event)',
        ]);
    }

    public function test_a_confirmed_booking_is_told_so_rather_than_to_pay(): void
    {
        $event = Event::factory()->autoConfirming()->create();

        $this->post(route('events.appoint', [$event->place, $event]), [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
            'quantity' => 1,
        ])->assertSessionHas('success', __('tickets.confirmed'));
    }

    public function test_the_door_still_checks_in_a_ticket_confirmed_on_booking(): void
    {
        $event = Event::factory()->autoConfirming()->create();
        $ticket = $this->appoint($event, 3);

        $checked = app(VerifyTicket::class)->markPaid($ticket, $event->place->user);

        $this->assertNotNull($checked->verified_at);
        $this->assertSame(3, $checked->arrived_quantity);
        $this->assertSame(TicketStatus::Paid, $checked->status);
    }

    public function test_the_flag_does_nothing_on_a_priced_event(): void
    {
        $event = Event::factory()->create(['price' => 5000, 'auto_confirm' => true]);

        $ticket = $this->appoint($event);

        $this->assertSame(TicketStatus::Pending, $ticket->status);
        $this->assertNotNull($ticket->hold_expires_at);
    }

    public function test_the_form_stores_the_flag_and_reads_it_back(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['price' => 0, 'auto_confirm' => '1']))
            ->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertTrue($event->auto_confirm);

        // Unticked means absent from the request, and absent means off.
        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->payload(['price' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($event->fresh()->auto_confirm);
    }

    // ------------------------------------------------------------------
    // Unlisted
    // ------------------------------------------------------------------

    public function test_an_unlisted_event_is_reachable_by_link_and_nowhere_else(): void
    {
        $event = Event::factory()->unlisted()->create();
        $place = $event->place;
        $url = route('events.show', [$place, $event]);

        $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('event.is_unlisted', true));

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0));
        $this->get("/{$place->slug}")->assertInertia(fn (AssertableInertia $page) => $page->has('upcoming', 0));
        $this->get('/sitemap.xml')->assertDontSee($url, false);
    }

    public function test_an_unlisted_event_is_not_a_sibling_of_a_listed_one(): void
    {
        $place = Place::factory()->create();
        $listed = Event::factory()->for($place)->create();
        Event::factory()->for($place)->unlisted()->create();

        $this->get(route('events.show', [$place, $listed]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('siblings', 0));
    }

    public function test_an_unlisted_event_takes_bookings(): void
    {
        $event = Event::factory()->unlisted()->create();

        $this->post(route('events.appoint', [$event->place, $event]), [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
            'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $event->tickets()->count());
    }

    public function test_the_form_round_trips_unlisted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['is_unlisted' => '1']))
            ->assertSessionHasNoErrors()
            // Live, by link only: the dialog must hand over the link and must
            // not claim the event is on the home page.
            ->assertSessionHas('saved_event.status', 'published')
            ->assertSessionHas('saved_event.unlisted', true);

        $event = Event::firstOrFail();
        $this->assertTrue($event->is_unlisted);

        $this->actingAs($owner)
            ->get("/owner/events/{$event->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event.is_unlisted', true));
    }

    // ------------------------------------------------------------------
    // Cover removal
    // ------------------------------------------------------------------

    public function test_a_cover_can_be_removed_from_a_published_event(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            ]))
            ->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertNotNull($event->cover_path);
        Storage::disk('public')->assertExists("events/{$event->id}/landscape.webp");

        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->payload(['remove_cover' => '1']))
            ->assertSessionHasNoErrors();

        $event->refresh();

        $this->assertNull($event->cover_path);
        $this->assertNull($event->cover_variants);
        $this->assertSame(EventStatus::Published, $event->status);
        Storage::disk('public')->assertMissing("events/{$event->id}/landscape.webp");
    }

    public function test_a_new_upload_wins_over_the_remove_box(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            ]));

        $event = Event::firstOrFail();

        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->payload([
                'remove_cover' => '1',
                'cover' => UploadedFile::fake()->image('new.jpg', 1200, 800),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($event->fresh()->cover_path);
    }

    // ------------------------------------------------------------------
    // Repeat from the form
    // ------------------------------------------------------------------

    public function test_a_cadence_on_the_form_makes_copies_on_save(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'repeat_cadence' => 'weekly',
                'repeat_count' => 3,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.copies', 3);

        $this->assertSame(4, Event::count());
        $this->assertSame(3, Event::where('status', EventStatus::Draft)->count());
    }

    public function test_no_cadence_means_no_copies(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/events', $this->payload(['repeat_cadence' => '', 'repeat_count' => 4]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.copies', 0);

        $this->assertSame(1, Event::count());
    }

    public function test_an_unknown_cadence_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->post('/owner/events', $this->payload(['repeat_cadence' => 'hourly', 'repeat_count' => 2]))
            ->assertSessionHasErrors('repeat_cadence');
    }

    // ------------------------------------------------------------------
    // Delete, or archive
    // ------------------------------------------------------------------

    public function test_an_event_nobody_holds_a_ticket_for_is_deleted_with_its_files(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            ]));

        $event = Event::firstOrFail();
        // A lapsed hold is not a holder.
        Ticket::factory()->for($event)->create([
            'status' => TicketStatus::Expired,
        ]);

        $this->actingAs($owner)
            ->delete("/owner/events/{$event->id}")
            ->assertRedirect('/owner/events')
            ->assertSessionHas('success', __('events.deleted'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        Storage::disk('public')->assertMissing("events/{$event->id}/landscape.webp");
    }

    public function test_an_event_with_paid_tickets_is_archived_instead(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create();
        Ticket::factory()->for($event)->create(['status' => TicketStatus::Paid]);

        $this->actingAs($owner)
            ->delete("/owner/events/{$event->id}")
            ->assertRedirect('/owner/events')
            ->assertSessionHas('warning', __('events.archived_instead'));

        $this->assertSame(EventStatus::Archived, $event->fresh()->status);
        $this->assertSame(1, $event->tickets()->count());
        $this->get(route('events.show', [$event->place, $event]))->assertNotFound();
    }

    public function test_a_still_held_booking_also_archives(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create();
        $this->appoint($event);

        $this->actingAs($owner)->delete("/owner/events/{$event->id}");

        $this->assertSame(EventStatus::Archived, $event->fresh()->status);
    }

    public function test_the_edit_page_says_how_many_hold_tickets(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create();
        Ticket::factory()->for($event)->create(['status' => TicketStatus::Paid]);
        Ticket::factory()->for($event)->create(['status' => TicketStatus::Cancelled]);

        $this->actingAs($owner)
            ->get("/owner/events/{$event->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('holders', 1));
    }

    public function test_another_owner_cannot_delete_it(): void
    {
        $event = Event::factory()->create();

        $this->actingAs($this->owner())
            ->delete("/owner/events/{$event->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_the_list_offers_an_archived_tab(): void
    {
        $owner = $this->owner();
        Event::factory()->for($owner->places()->first())->create(['status' => EventStatus::Archived]);

        $this->actingAs($owner)
            ->get('/owner/events?status=archived')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('counts.archived', 1)
                ->where('filter', 'archived')
                ->has('events', 1));
    }
}
