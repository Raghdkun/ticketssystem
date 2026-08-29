<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Location;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlacePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_venue_has_a_page_of_its_own(): void
    {
        $place = Place::factory()->create();
        Location::factory()->for($place)->primary()->create(['name_en' => 'Main Hall']);

        Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'starts_at' => now()->addMonth(),
            'title_en' => 'Coming Soon',
        ]);

        $this->get("/{$place->slug}?lang=en")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/place')
                ->where('place.name_en', $place->name_en)
                ->has('upcoming', 1)
                ->where('upcoming.0.title_en', 'Coming Soon')
                ->has('locations', 1)
            );
    }

    public function test_past_and_upcoming_are_told_apart(): void
    {
        $place = Place::factory()->create();

        Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'starts_at' => now()->subMonth(),
            'appointments_close_at' => now()->subMonth()->subDay(),
        ]);
        Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'starts_at' => now()->addMonth(),
        ]);

        $this->get("/{$place->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('upcoming', 1)->has('past', 1));
    }

    public function test_it_shows_only_what_the_public_may_see(): void
    {
        $place = Place::factory()->create();

        foreach ([EventStatus::Draft, EventStatus::PendingReview, EventStatus::Archived] as $status) {
            Event::factory()->for($place)->create([
                'status' => $status,
                'starts_at' => now()->addMonth(),
            ]);
        }

        $this->get("/{$place->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('upcoming', 0)->has('past', 0));
    }

    public function test_a_deactivated_venue_is_not_reachable(): void
    {
        $place = Place::factory()->create(['is_active' => false]);

        $this->get("/{$place->slug}")->assertNotFound();
    }

    public function test_it_does_not_shadow_the_application_s_own_paths(): void
    {
        // One free segment sits at the end of the routing table, so every
        // fixed path has to be matched before it gets a look in.
        foreach (['/login', '/privacy', '/terms', '/my-tickets'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_a_shared_venue_link_unfurls_as_the_venue(): void
    {
        $place = Place::factory()->create(['name_en' => 'Qanawat Theatre']);

        $html = $this->get("/{$place->slug}?lang=en")->assertOk()->getContent();

        $this->assertStringContainsString(
            '<meta property="og:title" content="Qanawat Theatre"',
            (string) $html
        );
    }
}
