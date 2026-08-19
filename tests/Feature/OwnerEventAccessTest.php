<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner event routes bind {event} by id, so authorisation is the only thing
 * standing between one venue and another's data. These assert it at the HTTP
 * layer, not just at the Gate.
 */
class OwnerEventAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->stranger = User::factory()->create();
        Place::factory()->for($this->stranger)->create();
        $this->event = Event::factory()->for(Place::factory()->for($this->owner))->create();
    }

    public function test_a_stranger_cannot_open_the_edit_page(): void
    {
        $this->actingAs($this->stranger)
            ->get(route('owner.events.edit', $this->event))
            ->assertForbidden();
    }

    public function test_a_stranger_cannot_update_the_event(): void
    {
        $this->actingAs($this->stranger)
            ->put(route('owner.events.update', $this->event), [
                'title_ar' => 'مخترق',
                'title_en' => 'Hijacked',
                'price' => 0,
                'currency' => 'SYP',
                'total_quantity' => 1,
                'max_per_appointment' => 1,
                'hold_hours' => 1,
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'appointments_close_at' => now()->addDays(6)->toDateTimeString(),
                'status' => 'published',
                'theme_mode' => 'auto',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $this->event->fresh()->title_en);
    }

    public function test_a_stranger_cannot_delete_the_event(): void
    {
        $this->actingAs($this->stranger)
            ->delete(route('owner.events.destroy', $this->event))
            ->assertForbidden();

        $this->assertModelExists($this->event);
    }

    public function test_a_guest_is_redirected_from_owner_routes(): void
    {
        $this->get(route('owner.events.index'))->assertRedirect(route('login'));
        $this->get(route('owner.events.edit', $this->event))->assertRedirect(route('login'));
        $this->get(route('owner.scan'))->assertRedirect(route('login'));
    }

    public function test_the_owner_only_sees_their_own_events_in_the_index(): void
    {
        Event::factory()->count(2)->for($this->stranger->places()->sole())->create();

        $this->actingAs($this->owner)
            ->get(route('owner.events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('owner/events/index')->has('events', 1));
    }

    /**
     * A super admin, or an owner not yet linked to a venue, has no place. The
     * events list must explain that rather than 404 on a failed lookup.
     */
    public function test_a_user_without_a_venue_sees_an_empty_state(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('owner.events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('owner/events/index')
                ->where('place', null)
                ->has('events', 0)
            );
    }

    public function test_a_user_without_a_venue_cannot_create_an_event(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('owner.events.create'))->assertForbidden();
    }

    public function test_the_owner_can_edit_their_own_event(): void
    {
        $this->actingAs($this->owner)
            ->get(route('owner.events.edit', $this->event))
            ->assertOk();
    }
}
