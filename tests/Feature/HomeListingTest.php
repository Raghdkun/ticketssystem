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

    public function test_it_hides_drafts_ended_events_and_inactive_venues(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, ['status' => EventStatus::Draft]);
        Event::factory()->ended()->for($place)->create(['status' => EventStatus::Published]);

        $dormant = Place::factory()->create(['is_active' => false]);
        $this->eventAt($dormant);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0));
    }

    /**
     * Booking closes at or before the start, so hiding closed events hid every
     * event on the day it happened -- the platform looked empty at exactly the
     * moment it was busiest. An event stays listed until it is over, and the
     * card says booking is closed.
     */
    public function test_an_event_whose_booking_has_closed_stays_listed_until_it_ends(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(6),
            'appointments_close_at' => now()->subHour(),
        ]);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('events', 1)
                ->where('events.0.is_open', false)
        );
    }

    public function test_an_event_with_no_end_time_lingers_a_few_hours_after_it_starts(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        $this->eventAt($place, [
            'starts_at' => now()->subHours(Event::LINGER_HOURS - 1),
            'ends_at' => null,
            'appointments_close_at' => now()->subDay(),
        ]);
        $this->eventAt($place, [
            'starts_at' => now()->subHours(Event::LINGER_HOURS + 1),
            'ends_at' => null,
            'appointments_close_at' => now()->subDay(),
        ]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1));
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

    /**
     * A bare home page with real events behind it reads as a dead platform.
     * When nothing is on, the last few events are shown as a record.
     */
    public function test_when_nothing_is_on_the_recent_events_are_shown_as_a_record(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        Event::factory()->ended()->for($place)->create(['status' => EventStatus::Published, 'title_en' => 'Last Week']);
        Event::factory()->ended()->for($place)->create(['status' => EventStatus::Draft, 'title_en' => 'Never Published']);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('events', 0)
                ->has('recent', 1)
                ->where('recent.0.title_en', 'Last Week')
                ->where('recent.0.ended', true)
                ->where('recent.0.is_open', false)
        );
    }

    public function test_the_record_is_not_shown_while_something_is_on_or_a_filter_is_active(): void
    {
        $place = Place::factory()->create(['is_active' => true]);
        Event::factory()->ended()->for($place)->create(['status' => EventStatus::Published]);
        $this->eventAt($place, ['title_en' => 'Tonight']);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1)->has('recent', 0));
        $this->get('/?q=nothing-here')->assertInertia(fn (AssertableInertia $page) => $page->has('events', 0)->has('recent', 0));
    }
}
