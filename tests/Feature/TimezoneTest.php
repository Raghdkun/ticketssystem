<?php

namespace Tests\Feature;

use App\Models\Event;
use Tests\TestCase;

/**
 * Event times mean what the owner typed.
 *
 * The form is a datetime-local field: an owner writes 19:00 because the
 * poster says 19:00. On UTC that was stored as 19:00 UTC and shown to a
 * visitor in Syria as 22:00.
 */
class TimezoneTest extends TestCase
{
    public function test_the_application_runs_in_local_time(): void
    {
        $this->assertSame('Asia/Damascus', config('app.timezone'));
    }

    public function test_a_typed_time_is_shown_back_as_the_same_wall_clock_time(): void
    {
        $event = Event::factory()->make(['starts_at' => '2026-10-01 19:00']);

        // The ISO string a page receives carries the local offset, so a
        // browser in Syria renders 19:00 rather than converting it.
        $this->assertSame('2026-10-01T19:00:00+03:00', $event->starts_at->toIso8601String());
    }
}
