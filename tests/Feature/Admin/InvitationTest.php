<?php

namespace Tests\Feature\Admin;

use App\Models\OwnerInvitation;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /**
     * @return array{0: OwnerInvitation, 1: string}
     */
    private function invite(string $email = 'new@example.com', bool $requiresApproval = false): array
    {
        ['invitation' => $invitation, 'token' => $token] = OwnerInvitation::mint(
            $email, $this->admin(), $requiresApproval
        );

        return [$invitation, $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Nader Hamza',
            'password' => 'a-long-enough-passphrase',
            'password_confirmation' => 'a-long-enough-passphrase',
            'place_name_ar' => 'مسرح قنوات',
            'place_name_en' => 'Qanawat Theatre',
            'location_name_ar' => 'القاعة الكبرى',
            'location_name_en' => 'Main Hall',
            'latitude' => 32.7093878,
            'longitude' => 36.5687496,
            'address_en' => 'Al-Qwatli Street',
            ...$overrides,
        ];
    }

    // --- The token ---------------------------------------------------------

    public function test_only_the_hash_is_stored(): void
    {
        [$invitation, $token] = $this->invite();

        // A leaked database must not hand somebody a working link.
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('owner_invitations', ['token_hash' => $token]);
    }

    public function test_a_wrong_token_is_not_found(): void
    {
        $this->invite();

        $this->get('/invite/'.str_repeat('a', 64))->assertNotFound();
    }

    public function test_an_expired_invitation_is_not_found(): void
    {
        [$invitation, $token] = $this->invite();
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get("/invite/{$token}")->assertNotFound();
        $this->post("/invite/{$token}", $this->payload())->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_an_invitation_works_exactly_once(): void
    {
        [, $token] = $this->invite();

        $this->post("/invite/{$token}", $this->payload())->assertRedirect();
        $this->assertSame(1, User::where('email', 'new@example.com')->count());

        // Second attempt with the same link creates nothing.
        $this->post("/invite/{$token}", $this->payload())->assertNotFound();
        $this->assertSame(1, User::where('email', 'new@example.com')->count());
    }

    // --- Accepting ---------------------------------------------------------

    public function test_accepting_creates_the_account_venue_and_first_location(): void
    {
        [, $token] = $this->invite();

        $this->post("/invite/{$token}", $this->payload())
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $place = $user->places()->firstOrFail();
        $location = $place->locations()->firstOrFail();

        $this->assertSame('Qanawat Theatre', $place->name_en);
        $this->assertSame('Main Hall', $location->name_en);
        $this->assertTrue($location->is_primary);
        $this->assertSame(32.7093878, (float) $location->latitude);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_email_comes_from_the_invitation_not_the_form(): void
    {
        [, $token] = $this->invite('invited@example.com');

        // A forwarded link must not become somebody else's account.
        $this->post("/invite/{$token}", $this->payload(['email' => 'attacker@example.com']));

        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_the_invited_tier_is_carried_over(): void
    {
        [, $token] = $this->invite('tiered@example.com', requiresApproval: true);

        $this->post("/invite/{$token}", $this->payload());

        $user = User::where('email', 'tiered@example.com')->firstOrFail();

        $this->assertTrue($user->requires_approval);
        $this->assertFalse($user->is_super_admin);
    }

    public function test_an_invitation_never_grants_administrator_access(): void
    {
        [, $token] = $this->invite();

        // Smuggled fields must not survive: neither flag is mass-assignable.
        $this->post("/invite/{$token}", $this->payload([
            'is_super_admin' => true,
            'requires_approval' => false,
        ]));

        $this->assertFalse(User::where('email', 'new@example.com')->firstOrFail()->is_super_admin);
    }

    public function test_a_failed_acceptance_leaves_nothing_behind(): void
    {
        [, $token] = $this->invite();

        // The venue name is required, so the whole thing must roll back.
        $this->post("/invite/{$token}", $this->payload(['place_name_en' => '']))
            ->assertSessionHasErrors('place_name_en');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertDatabaseCount('places', 0);
        $this->assertNull(OwnerInvitation::first()->accepted_at);
    }

    // --- Issuing -----------------------------------------------------------

    public function test_an_owner_cannot_invite_anybody(): void
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create();

        $this->actingAs($owner)->get('/admin/invitations')->assertForbidden();
        $this->actingAs($owner)
            ->post('/admin/invitations', ['email' => 'x@example.com', 'requires_approval' => false])
            ->assertForbidden();

        $this->assertDatabaseCount('owner_invitations', 0);
    }

    public function test_an_existing_account_cannot_be_invited_again(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->post('/admin/invitations', ['email' => $existing->email, 'requires_approval' => false])
            ->assertSessionHasErrors('email');
    }

    public function test_a_pending_invitation_is_not_duplicated(): void
    {
        $this->invite('pending@example.com');

        $this->actingAs($this->admin())
            ->post('/admin/invitations', ['email' => 'pending@example.com', 'requires_approval' => false])
            ->assertSessionHasErrors('email');
    }

    public function test_the_link_is_shown_once_and_carries_a_raw_token(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/admin/invitations', ['email' => 'fresh@example.com', 'requires_approval' => false]);

        $link = session('invitation_link');

        $this->assertNotNull($link);

        // Following it must actually work, and its token must not be the
        // stored value.
        $token = basename((string) $link);
        $this->assertSame(
            OwnerInvitation::hash($token),
            OwnerInvitation::where('email', 'fresh@example.com')->firstOrFail()->token_hash
        );
        $this->get("/invite/{$token}")->assertOk();
    }

    public function test_the_link_reaches_the_page_it_is_meant_for(): void
    {
        // The shared flash prop is rebuilt from named session keys, so
        // anything a controller flashes under another name is dropped without
        // a word -- which is exactly what happened the first time.
        $this->actingAs($this->admin())
            ->post('/admin/invitations', ['email' => 'shown@example.com', 'requires_approval' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/invitations')
            ->assertInertia(fn ($page) => $page->has('flash.invitation_link'));
    }

    public function test_a_pending_invitation_can_be_revoked(): void
    {
        [$invitation, $token] = $this->invite();

        $this->actingAs($this->admin())
            ->delete("/admin/invitations/{$invitation->id}")
            ->assertRedirect();

        $this->get("/invite/{$token}")->assertNotFound();
    }
}
