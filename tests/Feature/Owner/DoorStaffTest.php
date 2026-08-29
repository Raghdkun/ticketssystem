<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OwnerInvitation;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Door staff can work a door and nothing else. Most of this file is about
 * what they cannot reach, because that is what makes the account safe to
 * hand to somebody helping for one night.
 */
class DoorStaffTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Place
    {
        return Place::factory()->for(User::factory())->create();
    }

    private function staffFor(Place $place): User
    {
        return User::factory()->create(['door_staff_for' => $place->id]);
    }

    // --- What they can do --------------------------------------------------

    public function test_they_can_work_the_door(): void
    {
        $place = $this->venue();
        $staff = $this->staffFor($place);

        $this->actingAs($staff)->get('/owner/scan')->assertOk();
        $this->actingAs($staff)->get('/owner/search')->assertOk();
    }

    public function test_they_can_verify_a_ticket_at_their_venue(): void
    {
        $place = $this->venue();
        $staff = $this->staffFor($place);
        $event = Event::factory()->for($place)->create(['status' => EventStatus::Published]);
        $ticket = Ticket::factory()->for($event)->create();

        $this->actingAs($staff)->get("/verify/{$ticket->public_token}")->assertOk();

        $this->actingAs($staff)
            ->post("/verify/{$ticket->public_token}/paid", ['arrived' => 1])
            ->assertRedirect();

        $this->assertSame('paid', $ticket->fresh()->status->value);
    }

    public function test_their_search_is_scoped_to_their_own_venue(): void
    {
        $place = $this->venue();
        $staff = $this->staffFor($place);

        $mine = Ticket::factory()->for(Event::factory()->for($place))->create(['full_name' => 'Rana Mine']);
        $theirs = Ticket::factory()->for(Event::factory()->for($this->venue()))->create(['full_name' => 'Rana Theirs']);

        $response = $this->actingAs($staff)->get('/owner/search?q=Rana')->assertOk();

        $this->assertStringContainsString($mine->full_name, $response->getContent());
        $this->assertStringNotContainsString($theirs->full_name, $response->getContent());
    }

    public function test_they_can_print_the_door_sheet(): void
    {
        $place = $this->venue();
        $event = Event::factory()->for($place)->create(['status' => EventStatus::Published]);

        $this->actingAs($this->staffFor($place))
            ->get("/owner/events/{$event->id}/door-sheet")
            ->assertOk();
    }

    // --- What they cannot ---------------------------------------------------

    public function test_they_cannot_verify_a_ticket_at_another_venue(): void
    {
        $staff = $this->staffFor($this->venue());
        $stranger = Ticket::factory()->for(Event::factory()->for($this->venue()))->create();

        $this->actingAs($staff)->get("/verify/{$stranger->public_token}")->assertForbidden();
        $this->actingAs($staff)
            ->post("/verify/{$stranger->public_token}/paid", ['arrived' => 1])
            ->assertForbidden();

        $this->assertSame('pending', $stranger->fresh()->status->value);
    }

    public function test_the_dashboard_sends_them_to_the_door_instead_of_the_money(): void
    {
        // The dashboard leads on what the venue took, which is not theirs.
        $this->actingAs($this->staffFor($this->venue()))
            ->get('/dashboard')
            ->assertRedirect(route('owner.scan'));
    }

    public function test_they_cannot_reach_anything_that_shapes_the_venue(): void
    {
        $place = $this->venue();
        $staff = $this->staffFor($place);
        $event = Event::factory()->for($place)->create();

        foreach ([
            '/owner/events',
            '/owner/events/create',
            "/owner/events/{$event->id}/edit",
            "/owner/events/{$event->id}/report",
            "/owner/events/{$event->id}/poster",
            '/owner/place',
            '/owner/locations',
            '/owner/staff',
        ] as $path) {
            $this->actingAs($staff)->get($path)->assertRedirect(route('owner.scan'));
        }
    }

    public function test_a_write_they_should_not_make_is_refused_outright(): void
    {
        // A redirect is a kindness for a page load; a POST gets a flat no.
        $this->actingAs($this->staffFor($this->venue()))
            ->post('/owner/events', ['title_en' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_they_cannot_invite_anybody(): void
    {
        $this->actingAs($this->staffFor($this->venue()))
            ->post('/owner/staff', ['email' => 'friend@example.com'])
            ->assertForbidden();

        $this->assertDatabaseCount('owner_invitations', 0);
    }

    // --- Being invited ------------------------------------------------------

    public function test_an_owner_invites_staff_to_their_own_venue(): void
    {
        $place = $this->venue();

        $this->actingAs($place->user)
            ->post('/owner/staff', ['email' => 'door@example.com'])
            ->assertSessionHasNoErrors();

        $invitation = OwnerInvitation::firstOrFail();

        $this->assertTrue($invitation->isForStaff());
        $this->assertSame($place->id, $invitation->place_id);
    }

    public function test_accepting_a_staff_invitation_makes_no_venue(): void
    {
        $place = $this->venue();
        ['token' => $token] = OwnerInvitation::mint(
            'door@example.com', $place->user, false, 7,
            OwnerInvitation::ROLE_STAFF, $place->id
        );

        $this->post("/invite/{$token}", [
            'name' => 'Door Hand',
            'password' => 'a-long-enough-passphrase',
            'password_confirmation' => 'a-long-enough-passphrase',
        ])->assertRedirect();

        $user = User::where('email', 'door@example.com')->firstOrFail();

        $this->assertSame($place->id, $user->door_staff_for);
        $this->assertTrue($user->isDoorStaff());
        // They join a venue that exists; they do not bring one.
        $this->assertSame(1, Place::count());
    }

    public function test_removing_staff_keeps_the_account_for_the_audit_trail(): void
    {
        $place = $this->venue();
        $staff = $this->staffFor($place);

        $this->actingAs($place->user)
            ->delete("/owner/staff/{$staff->id}")
            ->assertRedirect();

        $staff->refresh();

        $this->assertNull($staff->door_staff_for);
        $this->assertNotNull($staff->banned_at);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    public function test_an_owner_cannot_remove_another_venues_staff(): void
    {
        $mine = $this->venue();
        $theirs = $this->staffFor($this->venue());

        $this->actingAs($mine->user)
            ->delete("/owner/staff/{$theirs->id}")
            ->assertNotFound();

        $this->assertNotNull($theirs->fresh()->door_staff_for);
    }
}
