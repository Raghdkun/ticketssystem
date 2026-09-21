<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Location;
use App\Models\OwnerInvitation;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Organisers without a room, and venues that open theirs.
 *
 * The event stays the organiser's -- their terms, their door -- only the
 * address is borrowed, and only from a venue that said yes in advance.
 */
class SharedLocationsTest extends TestCase
{
    use RefreshDatabase;

    private function venue(bool $sharing = true): Place
    {
        $place = Place::factory()->for(User::factory())->create([
            'name_en' => 'Qanawat Hall', 'name_ar' => 'قاعة قنوات', 'shares_locations' => $sharing,
        ]);
        Location::factory()->for($place)->create(['name_en' => 'Main Hall', 'name_ar' => 'القاعة الكبرى', 'is_primary' => true]);

        return $place;
    }

    private function organiser(): Place
    {
        return Place::factory()->organiser()->for(User::factory())->create([
            'name_en' => 'Night Events Co', 'name_ar' => 'شركة سهرات',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل', 'title_en' => 'Concert',
            'price' => 0, 'currency' => 'SYP', 'total_quantity' => 100,
            'max_per_appointment' => 4, 'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            'status' => EventStatus::Published->value,
            ...$overrides,
        ];
    }

    public function test_an_organiser_sees_shared_locations_and_must_pick_one(): void
    {
        $venue = $this->venue();
        $closed = $this->venue(sharing: false);
        $organiser = $this->organiser();

        $this->actingAs($organiser->user)
            ->get('/owner/events/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('needs_location', true)
                ->has('locations', 1)
                ->where('locations.0.host_en', 'Qanawat Hall'));

        // No location: refused. The closed venue's room: refused.
        $this->actingAs($organiser->user)
            ->post('/owner/events', $this->payload())
            ->assertSessionHasErrors('location_id');
        $this->actingAs($organiser->user)
            ->post('/owner/events', $this->payload(['location_id' => $closed->locations()->value('id')]))
            ->assertSessionHasErrors('location_id');
        $this->assertSame(0, Event::count());

        // The shared room: the event is the organiser's, held at the venue.
        $this->actingAs($organiser->user)
            ->post('/owner/events', $this->payload(['location_id' => $venue->locations()->value('id')]))
            ->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertSame($organiser->id, $event->place_id);
        $this->assertSame('Qanawat Hall', $event->hostPlace()?->name_en);
    }

    public function test_a_venue_owner_keeps_their_default_and_sees_others_rooms_only_when_shared(): void
    {
        $mine = $this->venue(sharing: false);
        $this->venue(sharing: true);

        $this->actingAs($mine->user)
            ->get('/owner/events/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('needs_location', false)
                ->has('locations', 2)
                ->where('locations.0.host_en', null)
                ->where('locations.1.host_en', 'Qanawat Hall'));

        // No location at all still falls back to their own primary.
        $this->actingAs($mine->user)
            ->post('/owner/events', $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertNull(Event::firstOrFail()->hostPlace());
    }

    public function test_the_public_pages_name_the_venue_and_the_organiser(): void
    {
        $venue = $this->venue();
        $organiser = $this->organiser();
        $event = Event::factory()->for($organiser)->create(['location_id' => $venue->locations()->value('id')]);

        $this->get(route('events.show', [$organiser, $event]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('event.host.name_en', 'Qanawat Hall')
                ->where('place.name_en', 'Night Events Co'));

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('events.0.host_name_en', 'Qanawat Hall')
            ->where('events.0.place_name_en', 'Night Events Co'));
    }

    public function test_the_venue_owner_sees_what_is_booked_into_their_room(): void
    {
        $venue = $this->venue();
        $organiser = $this->organiser();
        Event::factory()->for($organiser)->create(['title_en' => 'Dabke Night', 'location_id' => $venue->locations()->value('id')]);

        $this->actingAs($venue->user)
            ->get('/owner/locations')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('shares', true)
                ->has('locations.0.hosted', 1)
                ->where('locations.0.hosted.0.title_en', 'Dabke Night')
                ->where('locations.0.hosted.0.organiser_en', 'Night Events Co'));
    }

    public function test_the_venue_owner_switches_sharing_from_their_venue_page(): void
    {
        $venue = $this->venue(sharing: false);

        $this->actingAs($venue->user)
            ->patch('/owner/place', ['name_ar' => 'قاعة', 'name_en' => 'Hall', 'shares_locations' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($venue->fresh()->shares_locations);

        $this->actingAs($venue->user)
            ->patch('/owner/place', ['name_ar' => 'قاعة', 'name_en' => 'Hall'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($venue->fresh()->shares_locations);
    }

    public function test_the_door_stays_with_the_organiser(): void
    {
        $venue = $this->venue();
        $organiser = $this->organiser();
        $event = Event::factory()->for($organiser)->create(['location_id' => $venue->locations()->value('id')]);
        $ticket = Ticket::factory()->for($event)->create();

        // The venue's owner does not verify an organiser's tickets; the
        // organiser does.
        $this->actingAs($venue->user)->get("/verify/{$ticket->public_token}")->assertForbidden();
        $this->actingAs($organiser->user)->get("/verify/{$ticket->public_token}")->assertOk();
    }

    public function test_an_organiser_invitation_creates_no_location_and_needs_no_room_to_be_set_up(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        ['token' => $token] = OwnerInvitation::mint('org@example.com', $admin, false);

        $this->post("/invite/{$token}", [
            'name' => 'Organiser',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'place_name_ar' => 'شركة',
            'place_name_en' => 'Events Co',
            'no_venue' => '1',
        ])->assertSessionHasNoErrors();

        $place = Place::where('name_en', 'Events Co')->firstOrFail();
        $this->assertTrue($place->isOrganiser());
        $this->assertSame(0, $place->locations()->count());

        $this->actingAs($place->user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('setup.location', true));
    }

    public function test_a_venue_invitation_still_requires_the_first_location(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        ['token' => $token] = OwnerInvitation::mint('venue@example.com', $admin, false);

        $this->post("/invite/{$token}", [
            'name' => 'Owner',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'place_name_ar' => 'قاعة',
            'place_name_en' => 'Hall',
        ])->assertSessionHasErrors(['location_name_ar', 'location_name_en']);
    }
}
