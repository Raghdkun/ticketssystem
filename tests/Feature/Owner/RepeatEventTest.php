<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Copying an event forward on a cadence.
 *
 * A weekly night is the same event with a different date, and retyping the
 * rules, perks, price and seat count every week is the most repetitive thing
 * an owner does here.
 */
class RepeatEventTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Place $place;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->place = Place::factory()->for($this->owner)->create();

        $this->event = Event::factory()->for($this->place)->create([
            'status' => EventStatus::Published,
            'title_en' => 'Dabke Night',
            'starts_at' => now()->next('Thursday')->setTime(20, 0),
            'ends_at' => now()->next('Thursday')->setTime(23, 0),
            'appointments_close_at' => now()->next('Thursday')->setTime(18, 0),
            'total_quantity' => 80,
            'price' => '25000.00',
        ]);

        $this->event->rules()->create(['body_ar' => 'قاعدة', 'body_en' => 'A rule', 'sort' => 0]);
        $this->event->perks()->create(['body_ar' => 'ميزة', 'body_en' => 'A drink', 'sort' => 0]);
    }

    private function repeat(array $payload = []): TestResponse
    {
        return $this->actingAs($this->owner)->post(
            "/owner/events/{$this->event->id}/repeat",
            [...['cadence' => 'weekly', 'count' => 4], ...$payload],
        );
    }

    public function test_it_creates_the_requested_number_of_copies(): void
    {
        $this->repeat()->assertRedirect('/owner/events');

        $this->assertSame(5, $this->place->events()->count());
    }

    public function test_each_copy_steps_one_cadence_further_out(): void
    {
        $this->repeat(['count' => 3]);

        $copies = $this->place->events()
            ->whereKeyNot($this->event->id)
            ->orderBy('starts_at')
            ->get();

        foreach ($copies as $i => $copy) {
            $this->assertTrue(
                $copy->starts_at->equalTo($this->event->starts_at->addWeeks($i + 1)),
                "Copy {$i} did not land one week further out than the last."
            );
        }
    }

    public function test_a_monthly_cadence_adds_months_not_thirty_days(): void
    {
        // "The first Thursday" drifts otherwise, and drift makes the whole
        // feature useless.
        $this->repeat(['cadence' => 'monthly', 'count' => 1]);

        $copy = $this->place->events()->whereKeyNot($this->event->id)->sole();

        $this->assertTrue($copy->starts_at->equalTo($this->event->starts_at->addMonth()));
    }

    public function test_the_whole_window_moves_together(): void
    {
        $this->repeat(['count' => 1]);

        $copy = $this->place->events()->whereKeyNot($this->event->id)->sole();

        // A copy whose booking closed before its own start date would be
        // born unbookable.
        $this->assertTrue($copy->ends_at->equalTo($this->event->ends_at->addWeek()));
        $this->assertTrue($copy->appointments_close_at->equalTo(
            $this->event->appointments_close_at->addWeek()
        ));
        $this->assertTrue($copy->appointments_close_at->lessThan($copy->starts_at));
    }

    public function test_copies_land_as_drafts_even_from_a_live_event(): void
    {
        $this->repeat(['count' => 2]);

        $copies = $this->place->events()->whereKeyNot($this->event->id)->get();

        foreach ($copies as $copy) {
            $this->assertSame(EventStatus::Draft, $copy->status);
        }
    }

    public function test_rules_and_perks_come_with_it(): void
    {
        $this->repeat(['count' => 1]);

        $copy = $this->place->events()->whereKeyNot($this->event->id)->sole();

        $this->assertSame('A rule', $copy->rules()->value('body_en'));
        $this->assertSame('A drink', $copy->perks()->value('body_en'));
        // Copied, not shared: editing next week's rules must not rewrite
        // what this week's attendees agreed to.
        $this->assertNotSame(
            $this->event->rules()->value('id'),
            $copy->rules()->value('id'),
        );
    }

    public function test_pricing_and_capacity_come_with_it(): void
    {
        $this->repeat(['count' => 1]);

        $copy = $this->place->events()->whereKeyNot($this->event->id)->sole();

        $this->assertSame(80, $copy->total_quantity);
        $this->assertSame('25000.00', $copy->price);
    }

    public function test_no_bookings_come_with_it(): void
    {
        Ticket::factory()->for($this->event)->create();

        $this->repeat(['count' => 1]);

        $copy = $this->place->events()->whereKeyNot($this->event->id)->sole();

        $this->assertSame(0, $copy->tickets()->count());
    }

    public function test_every_copy_gets_its_own_slug(): void
    {
        $this->repeat(['count' => 6]);

        $slugs = $this->place->events()->pluck('slug')->all();

        $this->assertSame($slugs, array_unique($slugs));
    }

    public function test_repeating_twice_does_not_collide(): void
    {
        $this->repeat(['count' => 2]);
        $this->repeat(['count' => 2]);

        $slugs = $this->place->events()->pluck('slug')->all();

        $this->assertSame($slugs, array_unique($slugs));
        $this->assertSame(5, count($slugs));
    }

    public function test_a_stranger_cannot_repeat_somebody_elses_event(): void
    {
        $intruder = User::factory()->create();
        Place::factory()->for($intruder)->create();

        $this->actingAs($intruder)
            ->post("/owner/events/{$this->event->id}/repeat", ['cadence' => 'weekly', 'count' => 4])
            ->assertForbidden();

        $this->assertSame(1, $this->place->events()->count());
    }

    public function test_the_count_is_capped(): void
    {
        $this->repeat(['count' => 99])->assertSessionHasErrors('count');

        $this->assertSame(1, $this->place->events()->count());
    }

    public function test_an_unknown_cadence_is_rejected(): void
    {
        $this->repeat(['cadence' => 'hourly'])->assertSessionHasErrors('cadence');

        $this->assertSame(1, $this->place->events()->count());
    }
}
