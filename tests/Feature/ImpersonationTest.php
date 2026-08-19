<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\ImpersonationLog;
use App\Models\Place;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->owner = User::factory()->create();
        Place::factory()->for($this->owner)->create();
    }

    public function test_a_super_admin_can_act_as_an_owner(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.owners.impersonate', $this->owner))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->owner);
        $this->assertSame($this->admin->id, session(ImpersonationController::SESSION_KEY));
    }

    /**
     * Full access means actions are indistinguishable from the owner's own,
     * so the audit log is the only record of who was really at the keyboard.
     */
    public function test_starting_and_stopping_are_both_recorded(): void
    {
        $this->actingAs($this->admin)->post(route('admin.owners.impersonate', $this->owner));

        $log = ImpersonationLog::sole();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame($this->owner->id, $log->target_id);
        $this->assertNull($log->ended_at);

        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.owners'));

        $this->assertNotNull($log->fresh()->ended_at);
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_an_owner_cannot_impersonate_anyone(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->owner)
            ->post(route('admin.owners.impersonate', $target))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->owner);
        $this->assertSame(0, ImpersonationLog::count());
    }

    public function test_a_super_admin_cannot_be_impersonated(): void
    {
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.owners.impersonate', $other))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->admin);
    }

    /**
     * Nesting would lose track of who the real actor is.
     */
    public function test_impersonation_cannot_be_nested(): void
    {
        $another = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.owners.impersonate', $this->owner));

        $this->post(route('admin.owners.impersonate', $another))->assertStatus(403);
    }

    public function test_stopping_without_impersonating_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('impersonation.stop'))
            ->assertStatus(400);
    }

    public function test_the_banner_state_is_shared_with_the_client(): void
    {
        $this->actingAs($this->admin)->post(route('admin.owners.impersonate', $this->owner));

        $this->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.impersonating.name', $this->owner->name));
    }

    public function test_no_banner_state_when_not_impersonating(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.impersonating', null));
    }

    public function test_a_super_admin_can_change_the_platform_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'app_name_en' => 'Swaida Tickets Hub',
                'app_name_ar' => 'مركز تذاكر السويداء',
            ])
            ->assertRedirect();

        $this->assertSame('Swaida Tickets Hub', app(Settings::class)->appName('en'));
        $this->assertSame('مركز تذاكر السويداء', app(Settings::class)->appName('ar'));
    }

    public function test_an_owner_cannot_change_platform_settings(): void
    {
        $this->actingAs($this->owner)
            ->post(route('admin.settings.update'), [
                'app_name_en' => 'Hijacked',
                'app_name_ar' => 'Hijacked',
            ])
            ->assertForbidden();
    }

    /**
     * SVG is script-carrying markup served from our own origin.
     */
    public function test_an_svg_logo_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'app_name_en' => 'X',
                'app_name_ar' => 'X',
                'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_the_default_brand_is_swaida_tickets_hub(): void
    {
        $this->assertSame('Swaida Tickets Hub', Settings::DEFAULTS['app_name_en']);
        $this->assertSame('مركز تذاكر السويداء', Settings::DEFAULTS['app_name_ar']);
    }
}
