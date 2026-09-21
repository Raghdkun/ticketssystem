<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\CommercialOffer;
use App\Models\Event;
use App\Models\EventCommercialSnapshot;
use App\Models\Place;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Commercial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Tests\TestCase;

/**
 * The commercial layer: offers a venue accepts, the terms an event freezes
 * when it is published, and service orders on top.
 */
class CommercialTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create([
            'representative_name' => 'Samer Haddad',
            'representative_role' => 'manager',
            'representative_phone' => '+963991234567',
        ]);

        return $owner;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function place(User $owner): Place
    {
        return $owner->places()->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل',
            'title_en' => 'Concert',
            'price' => 25000,
            'currency' => 'SYP',
            'total_quantity' => 100,
            'max_per_appointment' => 4,
            'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            'status' => EventStatus::Published->value,
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------------
    // Offers
    // ------------------------------------------------------------------

    public function test_an_administrator_drafts_edits_and_sends_an_offer(): void
    {
        $admin = $this->admin();
        $place = $this->place($this->owner());

        $this->actingAs($admin)
            ->post('/admin/commercial-offers', [
                'place_id' => $place->id,
                'title_ar' => 'العرض',
                'title_en' => 'Offer',
                'fee_type' => 'percentage',
                'fee_value' => 5,
                'fee_payer' => 'customer',
                'settlement_days' => 3,
                'currency' => 'SYP',
            ])
            ->assertSessionHasNoErrors();

        $offer = CommercialOffer::firstOrFail();
        $this->assertTrue($offer->isDraft());
        $this->assertSame($admin->id, $offer->created_by);

        $this->actingAs($admin)
            ->patch("/admin/commercial-offers/{$offer->id}", [
                'place_id' => $place->id,
                'title_ar' => 'العرض',
                'title_en' => 'Offer',
                'fee_type' => 'percentage',
                'fee_value' => 7,
                'fee_payer' => 'organizer',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('7.00', $offer->fresh()->fee_value);

        $this->actingAs($admin)->post("/admin/commercial-offers/{$offer->id}/send")->assertRedirect();

        $offer->refresh();
        $this->assertTrue($offer->isSent());
        $this->assertSame($offer->hashContent(), $offer->content_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'commercial_offer_sent']);

        // Editing after sending is refused before the model even sees it.
        $this->actingAs($admin)
            ->patch("/admin/commercial-offers/{$offer->id}", ['place_id' => $place->id, 'title_ar' => 'x', 'title_en' => 'x', 'fee_type' => 'fixed', 'fee_payer' => 'split'])
            ->assertStatus(409);
    }

    public function test_a_percentage_fee_over_a_hundred_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/commercial-offers', [
                'place_id' => $this->place($this->owner())->id,
                'title_ar' => 'x', 'title_en' => 'x',
                'fee_type' => 'percentage', 'fee_value' => 150, 'fee_payer' => 'customer',
            ])
            ->assertSessionHasErrors('fee_value');
    }

    public function test_a_sent_offer_cannot_be_rewritten(): void
    {
        $offer = CommercialOffer::factory()->sent()->create();

        $this->expectException(LogicException::class);

        $offer->update(['fee_value' => 99]);
    }

    public function test_the_venue_accepts_with_a_ticked_box_and_the_row_becomes_the_record(): void
    {
        $owner = $this->owner();
        $offer = CommercialOffer::factory()->sent()->for($this->place($owner))->create();

        $this->actingAs($owner)
            ->post("/owner/commercial-offers/{$offer->id}/accept", [])
            ->assertSessionHasErrors('accept');

        $this->actingAs($owner)
            ->post("/owner/commercial-offers/{$offer->id}/accept", ['accept' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/owner/agreements');

        $offer->refresh();
        $this->assertTrue($offer->isAccepted());
        $this->assertSame($owner->id, $offer->accepted_by);
        $this->assertSame('Samer Haddad', $offer->representative_name);
        $this->assertSame('manager', $offer->representative_role);
        $this->assertSame('clickwrap_verified_account', $offer->acceptance_method);
        $this->assertNotNull($offer->ip);
        $this->assertSame($offer->hashContent(), $offer->content_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'commercial_offer_accepted', 'actor_id' => $owner->id]);

        $this->assertSame($offer->id, app(Commercial::class)->currentOffer($this->place($owner))?->id);
    }

    public function test_accepting_a_newer_offer_supersedes_the_old_one(): void
    {
        $owner = $this->owner();
        $place = $this->place($owner);
        $old = CommercialOffer::factory()->accepted()->for($place)->create();
        $new = CommercialOffer::factory()->sent()->for($place)->create(['fee_value' => 8]);

        $this->actingAs($owner)->post("/owner/commercial-offers/{$new->id}/accept", ['accept' => '1']);

        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertSame($new->id, $old->fresh()->superseded_by_id);
        $this->assertSame($new->id, app(Commercial::class)->currentOffer($place)?->id);
    }

    public function test_sending_a_new_offer_withdraws_one_still_waiting(): void
    {
        $place = $this->place($this->owner());
        $waiting = CommercialOffer::factory()->sent()->for($place)->create();
        $draft = CommercialOffer::factory()->for($place)->create();

        app(Commercial::class)->sendOffer($draft, $this->admin());

        $this->assertSame('superseded', $waiting->fresh()->status);
        $this->assertTrue($draft->fresh()->isSent());
    }

    public function test_the_venue_can_decline_and_an_expired_offer_cannot_be_accepted(): void
    {
        $owner = $this->owner();
        $place = $this->place($owner);

        $declined = CommercialOffer::factory()->sent()->for($place)->create();
        $this->actingAs($owner)->post("/owner/commercial-offers/{$declined->id}/reject")->assertRedirect('/owner/agreements');
        $this->assertSame('rejected', $declined->fresh()->status);

        $expired = CommercialOffer::factory()->sent()->for($place)->create(['valid_until' => now()->subDay()]);
        $this->actingAs($owner)
            ->post("/owner/commercial-offers/{$expired->id}/accept", ['accept' => '1'])
            ->assertSessionHasErrors('accept');
        $this->assertTrue($expired->fresh()->isSent());
    }

    public function test_another_venue_cannot_see_or_accept_an_offer(): void
    {
        $offer = CommercialOffer::factory()->sent()->create();
        $stranger = $this->owner();

        $this->actingAs($stranger)->get("/owner/commercial-offers/{$offer->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/owner/commercial-offers/{$offer->id}/accept", ['accept' => '1'])->assertNotFound();
        $this->assertTrue($offer->fresh()->isSent());
    }

    public function test_an_owner_cannot_reach_the_administration(): void
    {
        $this->actingAs($this->owner())->get('/admin/commercial-offers')->assertForbidden();
        $this->actingAs($this->owner())->get('/admin/service-orders')->assertForbidden();
        $this->actingAs($this->owner())->get('/admin/acceptances')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Event snapshot
    // ------------------------------------------------------------------

    public function test_publishing_a_paid_event_needs_the_acknowledgement_and_freezes_the_terms(): void
    {
        $owner = $this->owner();
        $offer = CommercialOffer::factory()->accepted()->for($this->place($owner))->create(['fee_value' => 5]);

        $this->actingAs($owner)
            ->post('/owner/events', $this->eventPayload())
            ->assertSessionHasErrors('commercial_ack');
        $this->assertSame(0, Event::count());

        $this->actingAs($owner)
            ->post('/owner/events', $this->eventPayload(['commercial_ack' => '1']))
            ->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $snapshot = $event->commercialSnapshot;

        $this->assertNotNull($snapshot);
        $this->assertSame($offer->id, $snapshot->commercial_offer_id);
        $this->assertSame('percentage', $snapshot->fee_type);
        $this->assertSame('5.00', $snapshot->fee_value);
        $this->assertSame('customer', $snapshot->fee_payer);
        $this->assertSame($owner->id, $snapshot->accepted_by);

        // A newer offer does not touch what this event was sold under.
        $newer = CommercialOffer::factory()->sent()->for($this->place($owner))->create(['fee_value' => 9]);
        $this->actingAs($owner)->post("/owner/commercial-offers/{$newer->id}/accept", ['accept' => '1']);
        $this->assertSame('5.00', $snapshot->fresh()->fee_value);

        // And editing the event later asks nothing more.
        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->eventPayload(['title_en' => 'Concert II']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, EventCommercialSnapshot::count());
    }

    public function test_a_free_event_a_draft_and_a_venue_without_an_offer_need_nothing(): void
    {
        $owner = $this->owner();

        // No offer: nothing to acknowledge.
        $this->actingAs($owner)->post('/owner/events', $this->eventPayload())->assertSessionHasNoErrors();

        CommercialOffer::factory()->accepted()->for($this->place($owner))->create();

        // Free: nothing to settle.
        $this->actingAs($owner)->post('/owner/events', $this->eventPayload(['price' => 0, 'title_en' => 'Free']))->assertSessionHasNoErrors();
        // Draft: not published yet.
        $this->actingAs($owner)->post('/owner/events', $this->eventPayload(['status' => 'draft', 'title_en' => 'Draft']))->assertSessionHasNoErrors();

        $this->assertSame(3, Event::count());
        $this->assertSame(0, EventCommercialSnapshot::count());
    }

    public function test_a_snapshot_is_written_once(): void
    {
        $event = Event::factory()->create();
        $snapshot = EventCommercialSnapshot::create([
            'event_id' => $event->id,
            'fee_type' => 'fixed',
            'fee_value' => 1000,
            'fee_payer' => 'organizer',
            'accepted_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $snapshot->update(['fee_value' => 2000]);
    }

    public function test_the_form_shows_the_terms_it_will_freeze(): void
    {
        $owner = $this->owner();
        CommercialOffer::factory()->accepted()->for($this->place($owner))->create(['fee_value' => 5]);

        $this->actingAs($owner)
            ->get('/owner/events/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('commercial.frozen', false)
                ->where('commercial.fee_value', 5));
    }

    // ------------------------------------------------------------------
    // Service orders
    // ------------------------------------------------------------------

    public function test_an_order_is_drafted_sent_confirmed_and_worked_through(): void
    {
        $admin = $this->admin();
        $owner = $this->owner();
        $place = $this->place($owner);
        $event = Event::factory()->for($place)->create();

        $this->actingAs($admin)
            ->post('/admin/service-orders', [
                'place_id' => $place->id,
                'event_id' => $event->id,
                'service_type' => 'door_staff',
                'title_ar' => 'تنظيم الدخول',
                'title_en' => 'Door staff',
                'quantity' => 4,
                'unit_price' => 50000,
                'currency' => 'SYP',
            ])
            ->assertSessionHasNoErrors();

        $order = ServiceOrder::firstOrFail();
        $this->assertSame(200000.0, $order->total());

        $this->actingAs($admin)->post("/admin/service-orders/{$order->id}/send")->assertRedirect();
        $this->assertTrue($order->fresh()->isSent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'service_order_sent']);

        $this->actingAs($owner)
            ->post("/owner/service-orders/{$order->id}/accept", [])
            ->assertSessionHasErrors('accept');
        $this->actingAs($owner)
            ->post("/owner/service-orders/{$order->id}/accept", ['accept' => '1'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('accepted', $order->status);
        $this->assertSame('Samer Haddad', $order->representative_name);
        $this->assertSame($order->hashContent(), $order->content_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service_order_accepted']);

        $this->actingAs($admin)->post("/admin/service-orders/{$order->id}/status", ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($admin)->post("/admin/service-orders/{$order->id}/status", ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);

        // Done is done.
        $this->actingAs($admin)->post("/admin/service-orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(409);
    }

    public function test_an_order_may_not_point_at_another_venues_event(): void
    {
        $place = $this->place($this->owner());
        $foreign = Event::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/service-orders', [
                'place_id' => $place->id,
                'event_id' => $foreign->id,
                'service_type' => 'promotion',
                'title_ar' => 'x', 'title_en' => 'x', 'quantity' => 1,
            ])
            ->assertSessionHasErrors('event_id');
    }

    public function test_another_venue_cannot_confirm_an_order(): void
    {
        $order = ServiceOrder::factory()->sent()->create();

        $this->actingAs($this->owner())
            ->post("/owner/service-orders/{$order->id}/accept", ['accept' => '1'])
            ->assertNotFound();
        $this->assertTrue($order->fresh()->isSent());
    }

    public function test_a_sent_order_cannot_be_rewritten(): void
    {
        $order = ServiceOrder::factory()->sent()->create();

        $this->expectException(LogicException::class);

        $order->update(['unit_price' => 1]);
    }

    // ------------------------------------------------------------------
    // The documents page and the audit log
    // ------------------------------------------------------------------

    public function test_the_documents_page_gathers_everything_the_venue_signed(): void
    {
        $owner = $this->owner();
        $place = $this->place($owner);
        $accepted = CommercialOffer::factory()->accepted()->for($place)->create();
        $pending = CommercialOffer::factory()->sent()->for($place)->create();
        ServiceOrder::factory()->sent()->for($place)->create();
        // Somebody else's paperwork stays theirs.
        CommercialOffer::factory()->sent()->create();

        $this->actingAs($owner)
            ->get('/owner/agreements')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('owner/documents')
                ->where('terms.current', null)
                ->where('offer.current.id', $accepted->id)
                ->has('offer.pending', 1)
                ->where('offer.pending.0.id', $pending->id)
                ->has('orders', 1)
                ->has('history', 1)
                ->where('history.0.kind', 'offer'));
    }

    public function test_an_account_with_no_venue_gets_an_empty_state_not_a_403(): void
    {
        $this->actingAs($this->admin())
            ->get('/owner/agreements')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('owner/documents')
                ->where('place', null));
    }

    public function test_the_audit_log_lists_and_filters_every_kind(): void
    {
        $owner = $this->owner();
        $place = $this->place($owner);
        $place->update(['name_en' => 'Qanawat Hall']);
        CommercialOffer::factory()->accepted()->for($place)->create();
        $order = ServiceOrder::factory()->sent()->for($place)->create();
        $this->actingAs($owner)->post("/owner/service-orders/{$order->id}/accept", ['accept' => '1']);

        $this->actingAs($this->admin())
            ->get('/admin/acceptances')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 2));

        $this->actingAs($this->admin())
            ->get('/admin/acceptances?kind=order')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.kind', 'order'));

        $this->actingAs($this->admin())
            ->get('/admin/acceptances?q=Qanawat')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 2));

        $this->actingAs($this->admin())
            ->get('/admin/acceptances?q=Nowhere')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));

        $this->actingAs($this->admin())
            ->get('/admin/acceptances?from='.now()->addDay()->toDateString())
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 0));
    }
}
