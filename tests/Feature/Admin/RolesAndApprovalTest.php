<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\PlatformStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function owner(bool $requiresApproval = false): User
    {
        $owner = User::factory()->create(['requires_approval' => $requiresApproval]);
        Place::factory()->for($owner)->create();

        return $owner;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل', 'title_en' => 'Concert',
            'description_ar' => 'وصف', 'description_en' => 'Description',
            'price' => 1000, 'currency' => 'SYP',
            'total_quantity' => 50, 'max_per_appointment' => 4, 'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            'status' => EventStatus::Published->value,
            ...$overrides,
        ];
    }

    // --- Roles -------------------------------------------------------------

    public function test_an_account_can_administer_and_own_a_venue_at_once(): void
    {
        $admin = $this->admin();
        Place::factory()->for($admin)->create();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->isOwner());

        // Both surfaces are reachable by the same account.
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/owner/events')->assertOk();
    }

    public function test_an_admin_can_promote_and_demote_someone_else(): void
    {
        $admin = $this->admin();
        $other = $this->owner();

        $this->actingAs($admin)
            ->patch("/admin/roles/{$other->id}", ['is_super_admin' => true, 'requires_approval' => false])
            ->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->is_super_admin);

        $this->actingAs($admin)
            ->patch("/admin/roles/{$other->id}", ['is_super_admin' => false, 'requires_approval' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_super_admin);
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $admin = $this->admin();
        $this->admin(); // a second one exists, so this is only the self rule

        $this->actingAs($admin)
            ->patch("/admin/roles/{$admin->id}", ['is_super_admin' => false, 'requires_approval' => false])
            ->assertSessionHasErrors('is_super_admin');

        $this->assertTrue($admin->fresh()->is_super_admin);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $second = $this->admin();

        // Registration is closed, so a platform with no administrator left
        // has no way to appoint one.
        $this->actingAs($admin)
            ->patch("/admin/roles/{$second->id}", ['is_super_admin' => false, 'requires_approval' => false])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->patch("/admin/roles/{$admin->id}", ['is_super_admin' => false, 'requires_approval' => false])
            ->assertSessionHasErrors('is_super_admin');

        $this->assertSame(1, User::where('is_super_admin', true)->count());
    }

    public function test_an_owner_cannot_reach_the_roles_page(): void
    {
        $this->actingAs($this->owner())->get('/admin/roles')->assertForbidden();
    }

    public function test_an_owner_cannot_promote_themselves(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch("/admin/roles/{$owner->id}", ['is_super_admin' => true, 'requires_approval' => false])
            ->assertForbidden();

        $this->assertFalse($owner->fresh()->is_super_admin);
    }

    public function test_a_role_change_is_recorded(): void
    {
        $admin = $this->admin();
        $other = $this->owner();

        $this->actingAs($admin)
            ->patch("/admin/roles/{$other->id}", ['is_super_admin' => true, 'requires_approval' => false]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'subject_id' => $other->id,
            'action' => 'role_changed',
        ]);
    }

    // --- Approval ----------------------------------------------------------

    public function test_an_unrestricted_owner_publishes_directly(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::Published, Event::first()->status);
    }

    public function test_an_approval_tier_owner_lands_in_pending_review(): void
    {
        $owner = $this->owner(requiresApproval: true);

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::PendingReview, Event::first()->status);
    }

    public function test_a_pending_event_is_not_publicly_reachable(): void
    {
        $owner = $this->owner(requiresApproval: true);
        $place = $owner->places()->first();

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload());
        $event = Event::first();

        // Invisible by construction: the public scope matches Published only.
        $this->get("/{$place->slug}/{$event->slug}")->assertNotFound();
    }

    public function test_an_approval_tier_owner_may_still_draft_freely(): void
    {
        $owner = $this->owner(requiresApproval: true);

        $this->actingAs($owner)
            ->post('/owner/events', $this->eventPayload(['status' => EventStatus::Draft->value]))
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::Draft, Event::first()->status);
    }

    public function test_an_admin_approving_makes_it_public(): void
    {
        $owner = $this->owner(requiresApproval: true);
        $place = $owner->places()->first();

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload());
        $event = Event::first();

        $this->actingAs($this->admin())
            ->post("/admin/events/{$event->id}/approve")
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
        $this->get("/{$place->slug}/{$event->slug}")->assertOk();
    }

    public function test_rejecting_returns_it_to_draft(): void
    {
        $owner = $this->owner(requiresApproval: true);

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload());
        $event = Event::first();

        $this->actingAs($this->admin())->post("/admin/events/{$event->id}/reject");

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_a_decision_only_applies_to_an_event_awaiting_one(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Published]);

        // A stale tab must not be able to un-publish something already live.
        $this->actingAs($this->admin())
            ->post("/admin/events/{$event->id}/approve")
            ->assertStatus(409);
    }

    public function test_an_owner_cannot_approve_their_own_event(): void
    {
        $owner = $this->owner(requiresApproval: true);

        $this->actingAs($owner)->post('/owner/events', $this->eventPayload());
        $event = Event::first();

        $this->actingAs($owner)
            ->post("/admin/events/{$event->id}/approve")
            ->assertForbidden();

        $this->assertSame(EventStatus::PendingReview, $event->fresh()->status);
    }

    public function test_editing_a_live_event_does_not_take_it_offline(): void
    {
        $owner = $this->owner(requiresApproval: true);
        $place = $owner->places()->first();
        $event = Event::factory()->for($place)->create(['status' => EventStatus::Published]);

        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->eventPayload(['title_en' => 'Corrected']))
            ->assertSessionHasNoErrors();

        // Fixing a typo must not pull the event the public is looking at.
        $this->assertSame(EventStatus::Published, $event->fresh()->status);
    }

    // --- Money -------------------------------------------------------------

    public function test_platform_stats_carry_no_income(): void
    {
        $stats = app(PlatformStats::class)->all();

        // What a venue takes at its own door is the venue's business. An
        // administrator sees scale, and reaches the figures by impersonating,
        // which leaves a record of who looked.
        $this->assertArrayNotHasKey('revenue', $stats);
        $this->assertArrayHasKey('owners', $stats);
    }

    public function test_an_admin_dashboard_never_carries_revenue(): void
    {
        $response = $this->actingAs($this->admin())->get('/dashboard')->assertOk();

        $this->assertStringNotContainsString('"revenue"', $response->getContent());
    }
}
