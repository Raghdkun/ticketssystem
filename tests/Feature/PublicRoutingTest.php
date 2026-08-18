<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the shape of the public URLs themselves.
 *
 * Generating a URL from a model and then requesting it is self-consistent even
 * when the binding key is wrong, so these tests assert the literal path.
 */
class PublicRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_urls_are_built_from_slugs_not_ids(): void
    {
        $event = Event::factory()->create();

        $url = route('events.show', [$event->place, $event]);

        $this->assertStringEndsWith("/{$event->place->slug}/{$event->slug}", $url);
        $this->assertStringNotContainsString("/{$event->id}", $url);
    }

    public function test_a_literal_slug_url_resolves(): void
    {
        $event = Event::factory()->create();

        $this->get("/{$event->place->slug}/{$event->slug}")->assertOk();
    }

    /**
     * Slugs are unique per place, so an event must not be reachable through a
     * place that does not own it.
     */
    public function test_an_event_is_not_reachable_through_a_foreign_place(): void
    {
        $event = Event::factory()->create();
        $otherPlace = Place::factory()->create();

        $this->get("/{$otherPlace->slug}/{$event->slug}")->assertNotFound();
    }

    public function test_two_places_may_reuse_the_same_event_slug(): void
    {
        $first = Event::factory()->create(['slug' => 'summer-night']);
        $second = Event::factory()->create(['slug' => 'summer-night']);

        $this->get("/{$first->place->slug}/summer-night")->assertOk();
        $this->get("/{$second->place->slug}/summer-night")->assertOk();
    }

    public function test_the_locale_switch_flips_document_direction(): void
    {
        $event = Event::factory()->create();
        $path = "/{$event->place->slug}/{$event->slug}";

        $this->get($path.'?lang=ar')->assertSee('dir="rtl"', false);
        $this->get($path.'?lang=en')->assertSee('dir="ltr"', false);
    }

    public function test_tickets_are_addressed_by_token_not_id(): void
    {
        $event = Event::factory()->create();
        $ticket = Ticket::factory()->for($event)->create();

        $this->assertStringEndsWith("/t/{$ticket->public_token}", route('tickets.show', $ticket));
        $this->get("/t/{$ticket->public_token}")->assertOk();
    }
}
