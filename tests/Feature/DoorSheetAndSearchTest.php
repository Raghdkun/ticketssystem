<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoorSheetAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->event = Event::factory()->for(Place::factory()->for($this->owner))->create(['price' => 1000]);
    }

    public function test_the_door_sheet_lists_live_bookings_only(): void
    {
        Ticket::factory()->for($this->event)->create(['full_name' => 'Amal', 'quantity' => 2]);
        Ticket::factory()->paid()->for($this->event)->create(['full_name' => 'Bilal', 'quantity' => 1]);
        Ticket::factory()->cancelled()->for($this->event)->create(['full_name' => 'Cancelled Person']);

        $this->actingAs($this->owner)
            ->get(route('owner.events.door_sheet', $this->event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('owner/door-sheet')
                ->has('rows', 2)
                ->where('summary.bookings', 2)
                ->where('summary.seats', 3)
                ->where('summary.paid_seats', 1)
                ->where('summary.outstanding_seats', 2)
            );
    }

    /**
     * The sheet exists so staff can collect money at the door, so the amount
     * outstanding has to be right.
     */
    public function test_the_sheet_shows_what_is_still_owed(): void
    {
        Ticket::factory()->for($this->event)->create(['full_name' => 'Unpaid', 'quantity' => 3]);
        Ticket::factory()->paid()->for($this->event)->create(['full_name' => 'Already Paid', 'quantity' => 2]);

        $this->actingAs($this->owner)
            ->get(route('owner.events.door_sheet', $this->event))
            ->assertInertia(fn ($page) => $page
                // Sorted by name: "Already Paid" precedes "Unpaid".
                ->where('rows.0.amount_due', 0)
                ->where('rows.1.amount_due', 3000)
            );
    }

    public function test_a_stranger_cannot_open_the_door_sheet(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('owner.events.door_sheet', $this->event))
            ->assertForbidden();
    }

    public function test_search_finds_tickets_across_every_event_the_owner_runs(): void
    {
        $other = Event::factory()->for($this->event->place)->create();
        Ticket::factory()->for($this->event)->create(['full_name' => 'Layla Haddad']);
        Ticket::factory()->for($other)->create(['full_name' => 'Layla Nasser']);

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => 'Layla']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('owner/search')->has('results', 2));
    }

    public function test_search_never_reaches_another_owners_tickets(): void
    {
        $stranger = User::factory()->create();
        $strangerEvent = Event::factory()->for(Place::factory()->for($stranger))->create();
        Ticket::factory()->for($strangerEvent)->create(['full_name' => 'Layla Secret']);
        Ticket::factory()->for($this->event)->create(['full_name' => 'Layla Mine']);

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => 'Layla']))
            ->assertInertia(fn ($page) => $page
                ->has('results', 1)
                ->where('results.0.full_name', 'Layla Mine')
            );
    }

    public function test_search_matches_a_reference_and_a_phone_number(): void
    {
        $ticket = Ticket::factory()->for($this->event)->create(['phone' => '+963991234567']);

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => substr($ticket->public_token, 0, 8)]))
            ->assertInertia(fn ($page) => $page->has('results', 1));

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => '991234567']))
            ->assertInertia(fn ($page) => $page->has('results', 1));
    }

    public function test_search_ignores_terms_that_are_too_short(): void
    {
        Ticket::factory()->for($this->event)->create(['full_name' => 'Ali']);

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => 'Al']))
            ->assertInertia(fn ($page) => $page->has('results', 0));
    }

    public function test_a_wildcard_does_not_return_everything(): void
    {
        Ticket::factory()->count(3)->for($this->event)->create();

        $this->actingAs($this->owner)
            ->get(route('owner.search', ['q' => '%%%']))
            ->assertInertia(fn ($page) => $page->has('results', 0));
    }
}
