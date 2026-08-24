<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A super admin owns no venue, so every owner-shaped screen has to behave
 * sensibly for them rather than dead-ending.
 */
class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * The owner empty state tells the reader to contact the platform
     * administrator. Shown to the platform administrator that is nonsense.
     */
    public function test_a_super_admin_sees_platform_figures_not_the_owner_empty_state(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for(Place::factory()->for($owner))->create(['price' => 1000]);
        Ticket::factory()->paid()->for($event)->create(['quantity' => 3]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hasPlace', false)
                ->has('platform')
                ->where('platform.owners', 1)
                ->where('platform.events', 1)
                ->where('platform.seats_paid', 3)
                ->where('platform.revenue', 3000)
            );
    }

    public function test_an_owner_without_a_venue_still_gets_the_plain_empty_state(): void
    {
        $orphan = User::factory()->create();

        $this->actingAs($orphan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hasPlace', false)
                // Not a super admin, so no platform figures are exposed.
                ->where('platform', null)
            );
    }

    public function test_an_owner_with_a_venue_sees_their_own_figures_only(): void
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('hasPlace', true)
                ->where('platform', null)
                ->has('stats')
            );
    }

    /**
     * The events list must not offer an action that the server refuses.
     */
    public function test_the_events_list_reports_no_venue_rather_than_offering_creation(): void
    {
        $this->actingAs($this->admin)
            ->get(route('owner.events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('owner/events/index')
                ->where('place', null)
                ->has('events', 0)
            );
    }

    public function test_creating_an_event_without_a_venue_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->get(route('owner.events.create'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('owner.events.store'), [
                'title_ar' => 'حفل', 'title_en' => 'Gig',
                'price' => 0, 'currency' => 'SYP',
                'total_quantity' => 10, 'max_per_appointment' => 2, 'hold_hours' => 24,
                'starts_at' => now()->addWeek()->toDateTimeString(),
                'appointments_close_at' => now()->addDays(6)->toDateTimeString(),
                'status' => 'draft', 'theme_mode' => 'auto',
                // A valid cover, so the request clears validation and actually
                // reaches the venue guard rather than failing earlier.
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_platform_figures_match_the_admin_screen(): void
    {
        $owner = User::factory()->create();
        Event::factory()->count(2)->for(Place::factory()->for($owner))->create();

        $dashboard = $this->actingAs($this->admin)->get(route('dashboard'));
        $adminPage = $this->actingAs($this->admin)->get(route('admin.owners'));

        $fromDashboard = $dashboard->viewData('page')['props']['platform'];
        $fromAdmin = $adminPage->viewData('page')['props']['stats'];

        // Both read the same service, so they can never drift apart.
        $this->assertSame($fromAdmin, $fromDashboard);
    }
}
