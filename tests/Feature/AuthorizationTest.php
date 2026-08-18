<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function eventOwnedBy(User $user): Event
    {
        return Event::factory()->for(Place::factory()->for($user))->create();
    }

    public function test_owner_may_manage_and_verify_their_own_event(): void
    {
        $owner = User::factory()->create();
        $event = $this->eventOwnedBy($owner);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $event));
        $this->assertTrue(Gate::forUser($owner)->allows('verifyTickets', $event));
    }

    public function test_owner_may_not_touch_another_owners_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = $this->eventOwnedBy($owner);

        $this->assertFalse(Gate::forUser($stranger)->allows('update', $event));
        $this->assertFalse(Gate::forUser($stranger)->allows('verifyTickets', $event));
    }

    public function test_super_admin_may_act_on_any_event(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $event = $this->eventOwnedBy($owner);

        $this->assertTrue(Gate::forUser($admin)->allows('update', $event));
        $this->assertTrue(Gate::forUser($admin)->allows('verifyTickets', $event));
    }

    public function test_banned_user_is_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->banned()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_user_reaches_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
