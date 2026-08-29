<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventWatcher;
use App\Models\Place;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The queue for a sold-out event.
 *
 * Sold out is not an ending on this platform: holds lapse and people cancel,
 * so seats come back regularly and there has to be somewhere to leave a name.
 */
class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    private function soldOutEvent(): Event
    {
        $place = Place::factory()->create(['is_active' => true]);

        $event = Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'total_quantity' => 2,
            'appointments_close_at' => now()->addWeek(),
        ]);

        Ticket::factory()->for($event)->create(['quantity' => 2]);

        return $event;
    }

    public function test_a_visitor_can_join_the_queue(): void
    {
        $event = $this->soldOutEvent();

        $this->post("/{$event->place->slug}/{$event->slug}/watch", [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
        ])->assertRedirect();

        $this->assertDatabaseHas('event_watchers', [
            'event_id' => $event->id,
            'full_name' => 'Layla Haddad',
            // Stored in E.164 so it matches everything else in the product.
            'phone' => '+963991234567',
        ]);
    }

    public function test_joining_twice_keeps_one_place_in_the_queue(): void
    {
        $event = $this->soldOutEvent();

        foreach (['Layla Haddad', 'Layla H'] as $name) {
            $this->post("/{$event->place->slug}/{$event->slug}/watch", [
                'full_name' => $name,
                'phone' => '0991234567',
            ]);
        }

        $this->assertSame(1, $event->watchers()->count());
        // The first name wins: a double tap is not a correction.
        $this->assertSame('Layla Haddad', $event->watchers()->value('full_name'));
    }

    public function test_a_closed_event_takes_no_more_names(): void
    {
        $event = $this->soldOutEvent();
        $event->update(['appointments_close_at' => now()->subDay()]);

        $this->post("/{$event->place->slug}/{$event->slug}/watch", [
            'full_name' => 'Layla Haddad',
            'phone' => '0991234567',
        ])->assertRedirect();

        $this->assertSame(0, $event->watchers()->count());
    }

    public function test_a_mobile_number_is_required_and_validated(): void
    {
        $event = $this->soldOutEvent();

        $this->post("/{$event->place->slug}/{$event->slug}/watch", [
            'full_name' => 'Layla Haddad',
            'phone' => 'not a number',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(0, $event->watchers()->count());
    }

    public function test_a_lapsed_hold_offers_its_seats_to_the_queue(): void
    {
        $event = $this->soldOutEvent();
        $watcher = EventWatcher::factory()->for($event)->create();

        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);

        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertNotNull($watcher->fresh()->notified_at);
    }

    public function test_a_cancellation_offers_its_seats_to_the_queue(): void
    {
        $event = $this->soldOutEvent();
        $watcher = EventWatcher::factory()->for($event)->create();
        $ticket = $event->tickets()->first();

        $this->post("/t/{$ticket->public_token}/release")->assertRedirect();

        $this->assertNotNull($watcher->fresh()->notified_at);
    }

    public function test_only_as_many_people_are_told_as_there_are_seats(): void
    {
        $event = $this->soldOutEvent();
        // Ten people waiting; the lapsing hold gives back two seats.
        EventWatcher::factory()->count(10)->for($event)->create();

        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);
        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertSame(2, $event->watchers()->whereNotNull('notified_at')->count());
    }

    public function test_the_queue_is_worked_in_the_order_people_joined(): void
    {
        $event = $this->soldOutEvent();
        $first = EventWatcher::factory()->for($event)->create();
        $second = EventWatcher::factory()->for($event)->create();
        $third = EventWatcher::factory()->for($event)->create();

        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);
        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertNotNull($first->fresh()->notified_at);
        $this->assertNotNull($second->fresh()->notified_at);
        $this->assertNull($third->fresh()->notified_at);
    }

    public function test_somebody_already_told_is_not_told_again(): void
    {
        $event = $this->soldOutEvent();
        $told = EventWatcher::factory()->notified()->for($event)->create();
        $stamped = $told->notified_at;

        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);
        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertEquals($stamped, $told->fresh()->notified_at);
    }

    public function test_nobody_is_told_when_the_event_has_already_closed(): void
    {
        $event = $this->soldOutEvent();
        $watcher = EventWatcher::factory()->for($event)->create();

        // Booking shut before the hold lapsed: the seat is real but useless.
        $event->update(['appointments_close_at' => now()->subMinute()]);
        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);

        $this->artisan('tickets:expire')->assertSuccessful();

        $this->assertNull($watcher->fresh()->notified_at);
    }

    public function test_a_watcher_with_a_device_is_pushed_to(): void
    {
        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => __FILE__,
            'services.fcm.access_token' => 'test-token',
        ]);
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

        $event = $this->soldOutEvent();
        EventWatcher::factory()->for($event)->create(['fcm_token' => 'device-1']);

        Ticket::query()->update(['hold_expires_at' => now()->subHour()]);
        $this->artisan('tickets:expire')->assertSuccessful();

        Http::assertSent(fn ($request) => $request['message']['token'] === 'device-1');
    }
}
