<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config()->set('services.fcm.project_id', 'demo-project');
        config()->set('services.fcm.credentials', '/tmp/creds.json');
        config()->set('services.fcm.access_token', 'test-token');
    }

    public function test_push_is_disabled_until_credentials_are_supplied(): void
    {
        $this->assertFalse(app(PushSender::class)->isConfigured());
    }

    public function test_subscribing_is_a_no_op_while_unconfigured(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson(route('tickets.push.store', $ticket), ['token' => 'device-1'])
            ->assertOk()
            ->assertJson(['enabled' => false]);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_it_never_calls_fcm_while_unconfigured(): void
    {
        Http::fake();

        $ticket = Ticket::factory()->create();
        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-1', 'locale' => 'ar']);

        $this->assertSame(0, app(PushSender::class)->ticketStatusChanged($ticket));

        Http::assertNothingSent();
    }

    public function test_a_device_can_subscribe_once_configured(): void
    {
        $this->configure();
        $ticket = Ticket::factory()->create();

        $this->postJson(route('tickets.push.store', $ticket), ['token' => 'device-1'])
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'ticket_id' => $ticket->id,
            'fcm_token' => 'device-1',
        ]);
    }

    public function test_subscribing_twice_does_not_duplicate_the_device(): void
    {
        $this->configure();
        $ticket = Ticket::factory()->create();

        foreach (range(1, 3) as $ignored) {
            $this->postJson(route('tickets.push.store', $ticket), ['token' => 'device-1']);
        }

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_it_sends_one_message_per_registered_device(): void
    {
        $this->configure();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

        $ticket = Ticket::factory()->paid()->create();
        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-1', 'locale' => 'ar']);
        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-2', 'locale' => 'en']);

        $this->assertSame(2, app(PushSender::class)->ticketStatusChanged($ticket->fresh()));

        Http::assertSentCount(2);
    }

    /**
     * A 404 or 410 from FCM means the device token is dead, so it should be
     * dropped rather than retried forever.
     */
    public function test_it_forgets_devices_fcm_reports_as_gone(): void
    {
        $this->configure();
        Http::fake(['fcm.googleapis.com/*' => Http::response([], 404)]);

        $ticket = Ticket::factory()->create();
        $ticket->pushSubscriptions()->create(['fcm_token' => 'dead-device', 'locale' => 'ar']);

        app(PushSender::class)->ticketStatusChanged($ticket->fresh());

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_device_can_unsubscribe(): void
    {
        $this->configure();
        $ticket = Ticket::factory()->create();
        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-1', 'locale' => 'ar']);

        $this->deleteJson(route('tickets.push.destroy', $ticket), ['token' => 'device-1'])
            ->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
