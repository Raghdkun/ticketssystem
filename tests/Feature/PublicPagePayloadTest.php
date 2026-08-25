<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPerk;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every key the public pages read must actually be present in the payload.
 *
 * The ticket page renders `event.perks.length`; when the presenter omitted
 * `perks` the whole page crashed client-side with no server error to notice.
 * A 200 response is not proof a page works.
 */
class PublicPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Keys the ticket page reads off `event`. There is deliberately no
     * `theme`: per-event palettes were retired in favour of one platform
     * identity.
     */
    private const TICKET_EVENT_KEYS = [
        'title_ar', 'title_en', 'starts_at', 'price', 'currency',
        'is_free', 'cover', 'perks',
    ];

    /** Keys the event page reads off `event`. */
    private const EVENT_PAGE_KEYS = [
        'title_ar', 'title_en', 'starts_at', 'price', 'currency', 'is_free',
        'cover', 'rules', 'perks', 'gallery', 'promo_video',
        'seats_remaining', 'is_open', 'max_per_appointment', 'slug',
    ];

    public function test_the_ticket_page_payload_has_every_key_it_renders(): void
    {
        $event = Event::factory()->create();
        EventPerk::factory()->count(2)->for($event)->create();
        $ticket = Ticket::factory()->for($event)->create();

        $response = $this->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            foreach (self::TICKET_EVENT_KEYS as $key) {
                $page->has('event.'.$key);
            }

            return $page;
        });
    }

    public function test_the_ticket_page_carries_the_events_perks(): void
    {
        $event = Event::factory()->create();
        EventPerk::factory()->for($event)->create(['body_en' => 'One free drink']);
        $ticket = Ticket::factory()->for($event)->create();

        $this->get(route('tickets.show', $ticket))
            ->assertInertia(fn ($page) => $page
                ->has('event.perks', 1)
                ->where('event.perks.0.body_en', 'One free drink')
            );
    }

    /**
     * An event with no perks must still send an empty array, never null:
     * the page calls `.length` on it unconditionally.
     */
    public function test_perks_is_an_array_even_when_empty(): void
    {
        $ticket = Ticket::factory()->create();

        $this->get(route('tickets.show', $ticket))
            ->assertInertia(fn ($page) => $page->has('event.perks', 0));
    }

    public function test_the_event_page_payload_has_every_key_it_renders(): void
    {
        $event = Event::factory()->create();

        $response = $this->get(route('events.show', [$event->place, $event]));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            foreach (self::EVENT_PAGE_KEYS as $key) {
                $page->has('event.'.$key);
            }

            return $page;
        });
    }
}
