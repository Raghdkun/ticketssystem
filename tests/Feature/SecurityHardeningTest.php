<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('password.email|127.0.0.1');
    }

    /**
     * Anyone able to self-register would hold an account on a venue-management
     * platform. Owners are provisioned by a super admin instead.
     */
    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_responses_carry_baseline_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Ticket URLs carry a bearer token in the path, so the referrer policy
     * must not let that token leak to third-party sites.
     */
    public function test_the_ticket_page_does_not_leak_its_token_via_referrer(): void
    {
        $ticket = Ticket::factory()->create();

        $this->get(route('tickets.show', $ticket))
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post('/forgot-password', ['email' => 'owner@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'owner@example.com'])
            ->assertStatus(429);
    }

    public function test_a_super_admin_can_provision_an_owner_and_venue(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.owners.store'), [
            'name' => 'Nadia Owner',
            'email' => 'nadia@example.com',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
            'place_name_en' => 'Cedar Hall',
            'place_name_ar' => 'قاعة الأرز',
            'whatsapp_number' => '0991234567',
        ])->assertRedirect();

        $user = User::where('email', 'nadia@example.com')->sole();

        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertNotNull($user->email_verified_at);
        // Account and venue are created together, never half-provisioned.
        $this->assertSame(1, $user->places()->count());
        $this->assertSame('cedar-hall', $user->places()->sole()->slug);
    }

    public function test_an_owner_cannot_provision_other_owners(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.owners.store'), [
                'name' => 'Escalation',
                'email' => 'escalate@example.com',
                'password' => 'Password!234',
                'password_confirmation' => 'Password!234',
                'place_name_en' => 'X',
                'place_name_ar' => 'X',
            ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
    }

    public function test_provisioning_is_atomic_when_validation_fails(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $before = User::count();

        $this->actingAs($admin)->post(route('admin.owners.store'), [
            'name' => 'No Venue',
            'email' => 'novenue@example.com',
            'password' => 'Password!234',
            'password_confirmation' => 'Password!234',
            // place names omitted
        ])->assertSessionHasErrors(['place_name_en', 'place_name_ar']);

        $this->assertSame($before, User::count());
    }

    /**
     * A '%' typed into the lookup must be treated as a literal, not as a
     * wildcard that returns every ticket the owner has.
     */
    public function test_like_wildcards_in_the_phone_lookup_are_escaped(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->for($owner)->create();
        $event = Event::factory()->for($place)->create();
        Ticket::factory()->count(3)->for($event)->create();

        $this->actingAs($owner)
            ->get(route('owner.scan', ['phone' => '%']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('results', 0));
    }
}
