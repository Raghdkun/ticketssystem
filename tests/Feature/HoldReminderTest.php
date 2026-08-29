<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The nudge that goes out before a hold lapses.
 *
 * Almost nobody lets a hold expire on purpose, so this is inventory the venue
 * would otherwise lose to somebody simply forgetting.
 */
class HoldReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => __FILE__, // Any readable file will do.
            'services.fcm.access_token' => 'test-token',
        ]);

        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);
    }

    private function holdExpiringIn(int $hours): Ticket
    {
        $ticket = Ticket::factory()
            ->for(Event::factory())
            ->create(['hold_expires_at' => now()->addHours($hours)]);

        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-1', 'locale' => 'ar']);

        return $ticket;
    }

    public function test_it_reminds_a_hold_that_is_about_to_lapse(): void
    {
        $ticket = $this->holdExpiringIn(2);

        $this->artisan('tickets:remind')->assertSuccessful();

        $this->assertNotNull($ticket->fresh()->reminder_sent_at);
        Http::assertSentCount(1);
    }

    public function test_it_leaves_a_hold_with_plenty_of_time_alone(): void
    {
        $ticket = $this->holdExpiringIn(20);

        $this->artisan('tickets:remind')->assertSuccessful();

        $this->assertNull($ticket->fresh()->reminder_sent_at);
        Http::assertNothingSent();
    }

    public function test_it_nudges_once_and_not_once_per_tick(): void
    {
        $ticket = $this->holdExpiringIn(2);

        $this->artisan('tickets:remind')->assertSuccessful();
        $this->artisan('tickets:remind')->assertSuccessful();
        $this->artisan('tickets:remind')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNotNull($ticket->fresh()->reminder_sent_at);
    }

    public function test_it_says_nothing_about_a_hold_that_has_already_lapsed(): void
    {
        // "Pay by 4pm" arriving at 5pm is worse than silence.
        $ticket = $this->holdExpiringIn(-1);

        $this->artisan('tickets:remind')->assertSuccessful();

        $this->assertNull($ticket->fresh()->reminder_sent_at);
        Http::assertNothingSent();
    }

    public function test_it_skips_a_holder_with_no_device_registered(): void
    {
        $ticket = Ticket::factory()
            ->for(Event::factory())
            ->create(['hold_expires_at' => now()->addHours(2)]);

        $this->artisan('tickets:remind')->assertSuccessful();

        $this->assertNull($ticket->fresh()->reminder_sent_at);
        Http::assertNothingSent();
    }

    public function test_it_does_nothing_at_all_when_push_is_unconfigured(): void
    {
        config(['services.fcm.credentials' => null]);

        $ticket = $this->holdExpiringIn(2);

        $this->artisan('tickets:remind')->assertSuccessful();

        $this->assertNull($ticket->fresh()->reminder_sent_at);
        Http::assertNothingSent();
    }

    public function test_the_reminder_carries_the_deadline(): void
    {
        $this->holdExpiringIn(2);

        $this->artisan('tickets:remind')->assertSuccessful();

        Http::assertSent(function ($request) {
            $body = $request['message']['notification']['body'] ?? '';

            // The point of the message is the time; a nudge without one is
            // just noise.
            return $body !== '' && ! str_contains($body, ':time');
        });
    }
}
