<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The public listing, which has to survive more than a dozen events.
 *
 * Filtering is on the server on purpose: the whole point is that this keeps
 * working at two hundred events, and shipping two hundred to the browser to
 * filter there would defeat it.
 */
class HomeListingTest extends TestCase
{
    use RefreshDatabase;

    private function eventAt(Place $place, array $attributes = []): Event
    {
        return Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'appointments_close_at' => now()->addWeek(),
            ...$attributes,
        ]);
    }

    public function test_it_lists_open_published_events(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['title_en' => 'Dabke Night']);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('welcome')
                ->has('events', 1)
                ->where('total', 1)
        );
    }

    public function test_it_hides_drafts_closed_events_and_inactive_venues(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['status' => EventStatus::Draft]);
        $this->eventAt($place, ['appointments_close_at' => now()->subDay()]);

        $dormant = Place::factory()->create(['is_active' => false]);
        $this->eventAt($dormant);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0));
    }

    public function test_it_can_be_filtered_to_one_venue(): void
    {
        $mine = Place::factory()->create(['is_active' => true, 'slug' => 'grand-hall']);
        $theirs = Place::factory()->create(['is_active' => true, 'slug' => 'rooftop']);

        $this->eventAt($mine);
        $this->eventAt($mine);
        $this->eventAt($theirs);

        $this->get('/?venue=grand-hall')->assertInertia(
            fn (AssertableInertia $page) => $page->has('events', 2)->where('filters.venue', 'grand-hall')
        );
    }

    public function test_it_can_be_searched_by_name_in_either_language(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['title_en' => 'Dabke Night', 'title_ar' => 'ليلة دبكة']);
        $this->eventAt($place, ['title_en' => 'Poetry Evening', 'title_ar' => 'أمسية شعرية']);

        $this->get('/?q=dabke')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));

        // Percent-encoded, as a browser sends it: an unencoded UTF-8 query
        // string is mangled by the test client, not by the application.
        $this->get('/?q='.urlencode('شعرية'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));
    }

    public function test_the_search_is_case_insensitive(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['title_en' => 'Dabke Night']);

        $this->get('/?q=DABKE')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));
    }

    public function test_filters_combine_rather_than_replace_each_other(): void
    {
        $mine = Place::factory()->create(['is_active' => true, 'slug' => 'grand-hall']);
        $theirs = Place::factory()->create(['is_active' => true, 'slug' => 'rooftop']);

        $this->eventAt($mine, ['title_en' => 'Dabke Night']);
        $this->eventAt($theirs, ['title_en' => 'Dabke Night']);

        $this->get('/?venue=grand-hall&q=dabke')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));
    }

    public function test_the_venue_filter_only_offers_venues_with_something_on(): void
    {
        $busy = Place::factory()->create(['is_active' => true]);
        $this->eventAt($busy);

        // A venue with only a draft is a dead end in the filter bar.
        $quiet = Place::factory()->create(['is_active' => true]);
        $this->eventAt($quiet, ['status' => EventStatus::Draft]);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('venues', 1)
                ->where('venues.0.slug', $busy->slug)
                ->where('venues.0.events', 1)
        );
    }

    public function test_the_listing_is_ordered_by_when_things_happen(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['title_en' => 'Later', 'starts_at' => now()->addMonth()]);
        $this->eventAt($place, ['title_en' => 'Sooner', 'starts_at' => now()->addDay()]);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page->where('events.0.title_en', 'Sooner')
        );
    }

    public function test_the_total_counts_matches_beyond_the_page_limit(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        Event::factory()->count(65)->for($place)->create([
            'status' => EventStatus::Published,
            'appointments_close_at' => now()->addWeek(),
        ]);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('events', 60)
                // Honest about what is being withheld, rather than implying
                // sixty is all there is.
                ->where('total', 65)
                ->where('limit', 60)
        );
    }

    public function test_a_search_matching_nothing_returns_an_empty_listing_not_everything(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['title_en' => 'Dabke Night']);

        $this->get('/?q=nothing-like-this')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0)->where('total', 0));
    }
}
