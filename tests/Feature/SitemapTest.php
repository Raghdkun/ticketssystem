<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_open_published_events(): void
    {
        $event = Event::factory()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee(route('events.show', [$event->place, $event]), false);
    }

    public function test_it_excludes_drafts_closed_events_and_inactive_venues(): void
    {
        $draft = Event::factory()->draft()->create();
        $closed = Event::factory()->closed()->create();
        $inactive = Event::factory()->for(Place::factory()->create(['is_active' => false]))->create();

        $body = $this->get('/sitemap.xml')->getContent();

        foreach ([$draft, $closed, $inactive] as $event) {
            $this->assertStringNotContainsString($event->slug, $body);
        }
    }

    /**
     * A ticket URL is a bearer token granting access to somebody's name and
     * phone number. It must never appear in a crawlable document.
     */
    public function test_it_never_exposes_ticket_urls(): void
    {
        $ticket = Ticket::factory()->create();

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString($ticket->public_token, $body);
        $this->assertStringNotContainsString('/t/', $body);
        $this->assertStringNotContainsString('/verify/', $body);
    }

    public function test_it_declares_both_locales(): void
    {
        Event::factory()->create();

        $this->get('/sitemap.xml')
            ->assertSee('hreflang="ar"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('hreflang="x-default"', false);
    }

    public function test_robots_blocks_the_private_areas(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $body = $response->getContent();

        // An absolute Sitemap URL: crawlers treat a relative one as malformed.
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);

        foreach (['/t/', '/verify/', '/owner/', '/admin/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $body);
        }

        $this->assertStringContainsString('Sitemap:', $body);
    }

    public function test_an_archived_event_drops_out_of_the_sitemap(): void
    {
        $event = Event::factory()->create();
        $this->get('/sitemap.xml')->assertSee($event->slug, false);

        $event->update(['status' => EventStatus::Archived]);

        $this->assertStringNotContainsString($event->slug, $this->get('/sitemap.xml')->getContent());
    }
}
