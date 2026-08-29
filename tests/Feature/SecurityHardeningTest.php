<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
    /**
     * There is one door in, and it is the invitation.
     *
     * An administrator typing somebody else's password was a second way to
     * create an account, and it bypassed the expiry, the single use and the
     * fixed address that make an invitation safe.
     */
    public function test_an_account_cannot_be_created_without_an_invitation(): void
    {
        $this->assertFalse(
            Route::has('admin.owners.store'),
            'Direct owner provisioning is back; invitations are meant to be the only door.'
        );

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post('/admin/owners', [
                'name' => 'Smuggled', 'email' => 'smuggled@example.com',
                'password' => 'a-long-enough-passphrase',
                'password_confirmation' => 'a-long-enough-passphrase',
                'place_name_ar' => 'قاعة', 'place_name_en' => 'Hall',
            ])
            ->assertStatus(405);

        $this->assertDatabaseMissing('users', ['email' => 'smuggled@example.com']);
    }

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
