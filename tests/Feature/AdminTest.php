<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_from_admin(): void
    {
        $this->get(route('admin.owners'))->assertRedirect(route('login'));
    }

    public function test_an_owner_cannot_reach_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.owners'))
            ->assertForbidden();
    }

    public function test_an_owner_cannot_ban_anyone(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.owners.ban', $target))
            ->assertForbidden();

        $this->assertNull($target->fresh()->banned_at);
    }

    public function test_a_super_admin_sees_the_owners_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.owners'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/owners')->has('stats'));
    }

    public function test_a_super_admin_can_ban_and_reinstate_an_owner(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.owners.ban', $owner))->assertRedirect();
        $this->assertNotNull($owner->fresh()->banned_at);

        $this->actingAs($admin)->post(route('admin.owners.unban', $owner))->assertRedirect();
        $this->assertNull($owner->fresh()->banned_at);
    }

    /**
     * A banned owner must lose access immediately, not at next login.
     */
    public function test_a_banned_owner_is_locked_out_of_their_dashboard(): void
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('owner.events.index'))->assertOk();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.owners.ban', $owner));

        $this->actingAs($owner->fresh())
            ->get(route('owner.events.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admins_cannot_be_banned(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.owners.ban', $other))->assertForbidden();
        $this->assertNull($other->fresh()->banned_at);
    }

    public function test_the_stats_count_seats_but_never_income(): void
    {
        $event = Event::factory()->create(['price' => 1000]);
        Ticket::factory()->paid()->for($event)->create(['quantity' => 3]);
        Ticket::factory()->for($event)->create(['quantity' => 2]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.owners'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.paid_tickets', 1)
                ->where('stats.pending_tickets', 1)
                ->where('stats.seats_paid', 3)
                // What a venue takes at its own door is the venue's business.
                // An administrator sees scale, and reaches the money only by
                // impersonating, which leaves a record of who looked.
                ->missing('stats.revenue')
            );
    }
}
