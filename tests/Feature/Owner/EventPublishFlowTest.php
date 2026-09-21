<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\CommercialOffer;
use App\Models\Event;
use App\Models\EventCommercialSnapshot;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving saves; the dialog publishes.
 *
 * The form carries no status any more. A new event is a draft, the dialog
 * after saving takes it live (or to review), and the same dialog can send
 * it back to draft or throw it away. Copies may go out at once, but only
 * on an explicit switch, and paid ones need the commercial acknowledgement.
 */
class EventPublishFlowTest extends TestCase
{
    use RefreshDatabase;

    private function owner(bool $requiresApproval = false): User
    {
        $owner = User::factory()->create();
        $owner->requires_approval = $requiresApproval;
        $owner->save();
        Place::factory()->for($owner)->create();

        return $owner;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل', 'title_en' => 'Concert',
            'price' => 25000, 'currency' => 'SYP', 'total_quantity' => 100,
            'max_per_appointment' => 4, 'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            ...$overrides,
        ];
    }

    public function test_saving_without_a_status_makes_a_draft_and_the_dialog_can_publish_it(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.status', 'draft')
            ->assertSessionHas('saved_event.needs_ack', false);

        $event = Event::firstOrFail();
        $this->assertSame(EventStatus::Draft, $event->status);

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/publish")
            ->assertRedirect('/owner/events')
            ->assertSessionHas('saved_event.status', 'published');

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
        $this->assertNotNull(session('saved_event.url'));
    }

    public function test_the_approval_tier_lands_in_review_from_the_dialog(): void
    {
        $owner = $this->owner(requiresApproval: true);
        $this->actingAs($owner)->post('/owner/events', $this->payload());
        $event = Event::firstOrFail();

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/publish")
            ->assertSessionHas('saved_event.status', 'pending_review')
            ->assertSessionHas('saved_event.requires_approval', true);

        $this->assertSame(EventStatus::PendingReview, $event->fresh()->status);
    }

    public function test_publishing_a_paid_event_under_an_offer_needs_the_acknowledgement_in_the_dialog(): void
    {
        $owner = $this->owner();
        $offer = CommercialOffer::factory()->accepted()->for($owner->places()->first())->create();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.needs_ack', true)
            ->assertSessionHas('saved_event.commercial.fee_payer', 'customer');

        $event = Event::firstOrFail();

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/publish")
            ->assertSessionHasErrors('commercial_ack');
        $this->assertSame(EventStatus::Draft, $event->fresh()->status);

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/publish", ['commercial_ack' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
        $this->assertSame($offer->id, $event->fresh()->commercialSnapshot?->commercial_offer_id);
    }

    public function test_editing_keeps_whatever_the_event_already_is(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create(['status' => EventStatus::Published]);

        $this->actingAs($owner)
            ->put("/owner/events/{$event->id}", $this->payload(['title_en' => 'Renamed']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.status', 'published');

        $this->assertSame(EventStatus::Published, $event->fresh()->status);
        $this->assertSame('Renamed', $event->fresh()->title_en);
    }

    public function test_unpublishing_returns_a_live_event_to_draft_and_only_a_draft_can_be_published(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create(['status' => EventStatus::Published]);

        $this->actingAs($owner)->post("/owner/events/{$event->id}/publish")->assertStatus(409);

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/unpublish")
            ->assertSessionHas('saved_event.status', 'draft');
        $this->assertSame(EventStatus::Draft, $event->fresh()->status);

        $this->actingAs($owner)->post("/owner/events/{$event->id}/unpublish")->assertStatus(409);
    }

    public function test_a_stranger_can_neither_publish_nor_unpublish(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Draft]);

        $this->actingAs($this->owner())->post("/owner/events/{$event->id}/publish")->assertForbidden();
        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_the_dialog_summary_describes_the_event(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['unlimited' => '1', 'is_unlisted' => '1', 'price' => 0, 'auto_confirm' => '1']))
            ->assertSessionHas('saved_event.summary', fn (array $summary) => $summary['total_quantity'] === null)
            ->assertSessionHas('saved_event.summary.is_free', true)
            ->assertSessionHas('saved_event.summary.auto_confirm', true)
            ->assertSessionHas('saved_event.unlisted', true);
    }

    // ------------------------------------------------------------------
    // Repeat and publish
    // ------------------------------------------------------------------

    public function test_copies_stay_drafts_unless_asked_to_go_out(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['repeat_cadence' => 'weekly', 'repeat_count' => 2]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Event::count());
        $this->assertSame(3, Event::where('status', EventStatus::Draft)->count());
    }

    public function test_publish_the_copies_puts_them_out_at_once(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'price' => 0, 'repeat_cadence' => 'weekly', 'repeat_count' => 2, 'repeat_publish' => '1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('saved_event.copies', 2);

        // The original is still a draft; the copies are live.
        $this->assertSame(1, Event::where('status', EventStatus::Draft)->count());
        $this->assertSame(2, Event::where('status', EventStatus::Published)->count());
    }

    public function test_published_copies_of_a_paid_event_need_the_acknowledgement_and_get_snapshots(): void
    {
        $owner = $this->owner();
        CommercialOffer::factory()->accepted()->for($owner->places()->first())->create();

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload(['repeat_cadence' => 'weekly', 'repeat_count' => 2, 'repeat_publish' => '1']))
            ->assertSessionHasErrors('commercial_ack');
        $this->assertSame(0, Event::count());

        $this->actingAs($owner)
            ->post('/owner/events', $this->payload([
                'repeat_cadence' => 'weekly', 'repeat_count' => 2, 'repeat_publish' => '1', 'commercial_ack' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Event::where('status', EventStatus::Published)->count());
        $this->assertSame(2, EventCommercialSnapshot::count());
    }

    public function test_the_approval_tier_gets_copies_in_review(): void
    {
        $owner = $this->owner(requiresApproval: true);
        $event = Event::factory()->for($owner->places()->first())->create(['price' => 0]);

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/repeat", ['cadence' => 'weekly', 'count' => 2, 'publish' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Event::where('status', EventStatus::PendingReview)->count());
    }

    public function test_the_list_dialog_publishes_copies_too(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create(['price' => 0]);

        $this->actingAs($owner)
            ->post("/owner/events/{$event->id}/repeat", ['cadence' => 'daily', 'count' => 3, 'publish' => '1'])
            ->assertRedirect('/owner/events');

        $this->assertSame(3, Event::where('status', EventStatus::Published)->whereKeyNot($event->id)->count());
    }
}
