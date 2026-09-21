<?php

namespace Tests\Feature;

use App\Enums\AgreementStatus;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\AgreementAcceptance;
use App\Models\AgreementVersion;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\Agreements;
use App\Services\Otp\OtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\TestCase;

/**
 * The Partner Terms: versioned and immutable, accepted by a venue with a
 * record of who and what, and enforced in front of everything that shapes
 * a venue -- and nothing else.
 */
class AgreementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create(['name_en' => 'Qanawat Hall', 'name_ar' => 'قاعة قنوات']);

        return $owner;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptance(AgreementVersion $version, array $overrides = []): array
    {
        return [
            'agreement_version_id' => $version->id,
            'legal_name' => 'Qanawat Hall LLC',
            'registration_number' => 'SY-12345',
            'representative_name' => 'Samer Haddad',
            'representative_title' => 'Manager',
            'representative_phone' => '0991234567',
            'accept' => '1',
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------
    // The gate
    // ------------------------------------------------------------------

    public function test_the_migration_leaves_a_draft_placeholder_and_no_acceptances(): void
    {
        $version = AgreementVersion::query()->where('version', '1.0')->first();

        $this->assertNotNull($version);
        $this->assertSame(AgreementStatus::Draft, $version->status);
        $this->assertSame(0, AgreementAcceptance::count());
        $this->assertNull(AgreementVersion::current());
    }

    public function test_with_nothing_published_an_owner_is_not_blocked(): void
    {
        $this->actingAs($this->owner())->get('/dashboard')->assertOk();
        $this->actingAs($this->owner())->get('/owner/events')->assertOk();
    }

    public function test_a_published_version_blocks_the_owner_until_accepted(): void
    {
        AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/owner/agreement');
        $this->actingAs($owner)->get('/owner/events')->assertRedirect('/owner/agreement');
        $this->actingAs($owner)->get('/owner/place')->assertRedirect('/owner/agreement');
        $this->actingAs($owner)->post('/owner/events', [])->assertRedirect('/owner/agreement');

        // The terms page itself, obviously, is reachable.
        $this->actingAs($owner)->get('/owner/agreement')->assertOk();
    }

    public function test_the_door_stays_open_while_the_terms_are_unaccepted(): void
    {
        AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)->get('/owner/scan')->assertOk();
        $this->actingAs($owner)->get('/owner/search')->assertOk();
    }

    public function test_door_staff_and_administrators_are_not_party_to_it(): void
    {
        AgreementVersion::factory()->published()->create();
        $place = Place::factory()->create();
        $staff = User::factory()->create(['door_staff_for' => $place->id]);

        $this->actingAs($staff)->get('/owner/scan')->assertOk();
        // Door staff never reach the terms: the venue gate sends them to the door.
        $this->actingAs($staff)->get('/owner/agreement')->assertRedirect('/owner/scan');

        $this->actingAs($this->admin())->get('/admin/owners')->assertOk();
        $this->actingAs($this->admin())->get('/dashboard')->assertOk();
    }

    public function test_an_impersonating_administrator_passes_the_gate(): void
    {
        AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->withSession([ImpersonationController::SESSION_KEY => $this->admin()->id])
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_buyers_are_untouched(): void
    {
        AgreementVersion::factory()->published()->create();
        $event = Event::factory()->create();

        $this->get('/')->assertOk();
        $this->get(route('events.show', [$event->place, $event]))->assertOk();
    }

    // ------------------------------------------------------------------
    // Accepting
    // ------------------------------------------------------------------

    public function test_accepting_records_who_what_and_when_and_opens_the_gate(): void
    {
        $version = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->from('/owner/agreement')
            ->post('/owner/agreement', $this->acceptance($version))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard');

        $acceptance = AgreementAcceptance::firstOrFail();
        $place = $owner->places()->first();

        $this->assertSame($version->id, $acceptance->agreement_version_id);
        $this->assertSame($place->id, $acceptance->place_id);
        $this->assertSame($owner->id, $acceptance->user_id);
        $this->assertSame('Qanawat Hall LLC', $acceptance->legal_name);
        $this->assertSame('Samer Haddad', $acceptance->representative_name);
        $this->assertSame('+963991234567', $acceptance->representative_phone);
        $this->assertSame($version->content_hash, $acceptance->content_hash);
        $this->assertSame('none', $acceptance->otp_channel);
        $this->assertNull($acceptance->otp_verified_at);
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertNotNull($acceptance->ip);

        // The identity is kept on the venue for next time.
        $this->assertSame('Qanawat Hall LLC', $place->fresh()->legal_name);
        $this->assertSame('+963991234567', $place->fresh()->representative_phone);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agreement_accepted',
            'actor_id' => $owner->id,
            'subject_id' => $owner->id,
        ]);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
    }

    public function test_the_checkbox_must_be_ticked_and_the_identity_complete(): void
    {
        $version = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($version, ['accept' => '0']))
            ->assertSessionHasErrors('accept');

        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($version, ['legal_name' => '', 'representative_phone' => 'nope']))
            ->assertSessionHasErrors(['legal_name', 'representative_phone']);

        $this->assertSame(0, AgreementAcceptance::count());
    }

    public function test_a_stale_form_for_a_superseded_version_is_refused(): void
    {
        $old = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $new = AgreementVersion::factory()->create();
        app(Agreements::class)->publish($new, $this->admin());

        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($old))
            ->assertSessionHasErrors('agreement_version_id');

        $this->assertSame(0, AgreementAcceptance::count());
    }

    public function test_accepting_twice_leaves_one_record(): void
    {
        $version = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/agreement', $this->acceptance($version));
        $this->actingAs($owner)->post('/owner/agreement', $this->acceptance($version, ['legal_name' => 'Somebody Else']));

        $this->assertSame(1, AgreementAcceptance::count());
        $this->assertSame('Qanawat Hall LLC', AgreementAcceptance::first()->legal_name);
    }

    public function test_the_page_shows_the_record_once_accepted(): void
    {
        $version = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();
        $this->actingAs($owner)->post('/owner/agreement', $this->acceptance($version));

        $this->actingAs($owner)
            ->get('/owner/agreement')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('owner/agreement')
                ->where('agreement.version', $version->version)
                ->where('accepted.legal_name', 'Qanawat Hall LLC')
                ->where('otp.enabled', false));
    }

    // ------------------------------------------------------------------
    // Versions
    // ------------------------------------------------------------------

    public function test_a_published_version_cannot_be_edited(): void
    {
        $version = AgreementVersion::factory()->published()->create();

        $this->expectException(LogicException::class);

        $version->update(['body_en' => 'Rewritten after signing']);
    }

    public function test_a_published_version_cannot_be_deleted(): void
    {
        $version = AgreementVersion::factory()->published()->create();

        $this->expectException(LogicException::class);

        $version->delete();
    }

    public function test_publishing_retires_the_previous_version_and_asks_everyone_again(): void
    {
        // 1.0 is the migration's own draft placeholder, so these are 5.x.
        $v1 = AgreementVersion::factory()->published()->create(['version' => '5.0']);
        $owner = $this->owner();
        $this->actingAs($owner)->post('/owner/agreement', $this->acceptance($v1));
        $this->actingAs($owner)->get('/dashboard')->assertOk();

        $v2 = AgreementVersion::factory()->create(['version' => '5.1', 'body_en' => 'New text']);
        app(Agreements::class)->publish($v2, $this->admin());

        $this->assertSame(AgreementStatus::Retired, $v1->fresh()->status);
        $this->assertNotNull($v1->fresh()->retired_at);
        $this->assertSame(AgreementStatus::Published, $v2->fresh()->status);
        $this->assertSame($v2->fresh()->hashContent(), $v2->fresh()->content_hash);
        $this->assertSame($v2->id, AgreementVersion::current()?->id);

        // Blocked again, told what they signed before, and the old record stands.
        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/owner/agreement');
        $this->actingAs($owner)
            ->get('/owner/agreement')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('agreement.version', '5.1')
                ->where('accepted', null)
                ->where('previous.version', '5.0'));
        $this->assertSame(1, AgreementAcceptance::where('agreement_version_id', $v1->id)->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'agreement_published']);
    }

    // ------------------------------------------------------------------
    // Administration
    // ------------------------------------------------------------------

    public function test_an_administrator_drafts_edits_publishes_and_sees_acceptances(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/agreements', [
                'version' => '1.1',
                'title_ar' => 'شروط',
                'title_en' => 'Terms',
                'body_ar' => 'نص',
                'body_en' => 'Text',
            ])
            ->assertSessionHasNoErrors();

        $draft = AgreementVersion::where('version', '1.1')->firstOrFail();
        $this->assertSame($admin->id, $draft->created_by);

        $this->actingAs($admin)
            ->patch("/admin/agreements/{$draft->id}", [
                'version' => '1.1',
                'title_ar' => 'شروط',
                'title_en' => 'Terms',
                'body_ar' => 'نص',
                'body_en' => 'Final text',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('Final text', $draft->fresh()->body_en);

        $this->actingAs($admin)->post("/admin/agreements/{$draft->id}/publish")->assertRedirect();
        $this->assertTrue($draft->fresh()->isPublished());

        // Editing after publishing is refused before the model even sees it.
        $this->actingAs($admin)
            ->patch("/admin/agreements/{$draft->id}", ['version' => '1.1', 'title_ar' => 'x', 'title_en' => 'x', 'body_ar' => 'x', 'body_en' => 'x'])
            ->assertStatus(409);
        $this->actingAs($admin)->delete("/admin/agreements/{$draft->id}")->assertStatus(409);

        $owner = $this->owner();
        $this->actingAs($owner)->post('/owner/agreement', $this->acceptance($draft->fresh()));

        $this->actingAs($admin)
            ->get("/admin/agreements/{$draft->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/agreements/show')
                ->where('agreement.status', 'published')
                ->has('acceptances', 1)
                ->where('acceptances.0.legal_name', 'Qanawat Hall LLC'));

        $this->actingAs($admin)
            ->get('/admin/agreements')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('outstanding.version', '1.1')
                ->has('outstanding.places', 0));
    }

    public function test_the_version_number_must_be_new_and_numeric(): void
    {
        $admin = $this->admin();
        AgreementVersion::factory()->create(['version' => '3.0']);

        $this->actingAs($admin)
            ->post('/admin/agreements', ['version' => '3.0', 'title_ar' => 'x', 'title_en' => 'x', 'body_ar' => 'x', 'body_en' => 'x'])
            ->assertSessionHasErrors('version');

        $this->actingAs($admin)
            ->post('/admin/agreements', ['version' => 'final', 'title_ar' => 'x', 'title_en' => 'x', 'body_ar' => 'x', 'body_en' => 'x'])
            ->assertSessionHasErrors('version');
    }

    public function test_an_owner_cannot_reach_the_administration(): void
    {
        $this->actingAs($this->owner())->get('/admin/agreements')->assertForbidden();
        $this->actingAs($this->owner())->post('/admin/agreements', [])->assertForbidden();
    }

    // ------------------------------------------------------------------
    // The one-time code
    // ------------------------------------------------------------------

    public function test_without_a_driver_no_code_is_asked_for(): void
    {
        $this->assertFalse(app(OtpChallenge::class)->isEnabled());

        $this->actingAs($this->owner())->post('/owner/agreement/code', ['representative_phone' => '0991234567'])->assertNotFound();
    }

    public function test_with_a_driver_the_code_is_required_and_checked(): void
    {
        config(['otp.driver' => 'log']);
        $this->app->forgetInstance(OtpChallenge::class);
        Cache::flush();

        $version = AgreementVersion::factory()->published()->create();
        $owner = $this->owner();

        // The code goes to the log; read it back from there.
        $sent = null;
        Log::shouldReceive('info')->once()->withArgs(function (string $message) use (&$sent) {
            $sent = substr($message, -6);

            return str_starts_with($message, 'OTP for +963991234567');
        });

        $this->actingAs($owner)
            ->post('/owner/agreement/code', ['representative_phone' => '0991234567'])
            ->assertSessionHas('otp_sent_to', '+963991234567');

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $sent);

        // No code, wrong code, right code.
        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($version))
            ->assertSessionHasErrors('otp_code');
        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($version, ['otp_code' => '000000']))
            ->assertSessionHasErrors('otp_code');
        $this->actingAs($owner)
            ->post('/owner/agreement', $this->acceptance($version, ['otp_code' => $sent]))
            ->assertSessionHasNoErrors();

        $acceptance = AgreementAcceptance::firstOrFail();
        $this->assertSame('log', $acceptance->otp_channel);
        $this->assertNotNull($acceptance->otp_verified_at);
    }

    public function test_a_code_is_single_use_and_gives_up_after_too_many_wrong_guesses(): void
    {
        config(['otp.driver' => 'log', 'otp.max_attempts' => 3]);
        $this->app->forgetInstance(OtpChallenge::class);
        Cache::flush();
        Log::shouldReceive('info')->andReturnNull();

        $otp = app(OtpChallenge::class);
        $otp->start('test', '+963991234567');

        $this->assertFalse($otp->verify('test', '+963991234567', '1'));
        $this->assertFalse($otp->verify('test', '+963991234567', '2'));
        $this->assertFalse($otp->verify('test', '+963991234567', '3'));
        // Thrown away: even the right code would fail now, and nothing is cached.
        $this->assertNull(Cache::get('otp:test:'.hash('sha256', '+963991234567')));
    }

    public function test_the_audit_log_names_the_acts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/agreements', ['version' => '9.0', 'title_ar' => 'x', 'title_en' => 'x', 'body_ar' => 'x', 'body_en' => 'x']);
        $draft = AgreementVersion::where('version', '9.0')->firstOrFail();
        $this->actingAs($admin)->delete("/admin/agreements/{$draft->id}");

        $this->assertSame(
            ['agreement_draft_deleted', 'agreement_drafted'],
            AuditLog::query()->whereIn('action', ['agreement_drafted', 'agreement_draft_deleted'])->orderByDesc('id')->pluck('action')->all(),
        );
    }
}
