<?php

namespace Tests\Feature;

use App\Actions\AppointTicket;
use App\Actions\VerifyTicket;
use App\Events\TicketStatusChanged;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_a_ticket_broadcasts_the_new_status(): void
    {
        EventFacade::fake([TicketStatusChanged::class]);

        $owner = User::factory()->create();
        $event = Event::factory()->for(Place::factory()->for($owner))->create();
        $ticket = Ticket::factory()->for($event)->create();

        app(VerifyTicket::class)->markPaid($ticket, $owner);

        EventFacade::assertDispatched(
            TicketStatusChanged::class,
            fn (TicketStatusChanged $e) => $e->ticket->is($ticket)
        );
    }

    public function test_appointing_broadcasts_so_the_owner_feed_updates(): void
    {
        EventFacade::fake([TicketStatusChanged::class]);

        $event = Event::factory()->create();

        app(AppointTicket::class)->handle($event, 'Nour', '+963991234567', 1, []);

        EventFacade::assertDispatched(TicketStatusChanged::class);
    }

    public function test_expiry_broadcasts_so_the_holder_page_greys_out(): void
    {
        EventFacade::fake([TicketStatusChanged::class]);

        $ticket = Ticket::factory()->lapsed()->create();

        $this->artisan('tickets:expire')->assertSuccessful();

        EventFacade::assertDispatched(
            TicketStatusChanged::class,
            fn (TicketStatusChanged $e) => $e->ticket->is($ticket)
        );
    }

    public function test_it_broadcasts_on_the_token_channel_and_the_private_place_channel(): void
    {
        $ticket = Ticket::factory()->create();

        $channels = (new TicketStatusChanged($ticket))->broadcastOn();

        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame("ticket.{$ticket->public_token}", $channels[0]->name);

        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertSame("private-place.{$ticket->event->place_id}", $channels[1]->name);
    }

    /**
     * The ticket channel is public, so its payload must never carry the
     * holder's name or phone number.
     */
    public function test_the_payload_carries_no_personal_data(): void
    {
        $ticket = Ticket::factory()->create([
            'full_name' => 'Layla Haddad',
            'phone' => '+963991234567',
        ]);

        $payload = (new TicketStatusChanged($ticket))->broadcastWith();

        $this->assertSame(['token', 'status', 'verified_at'], array_keys($payload));
        $this->assertStringNotContainsString('Layla', json_encode($payload));
        $this->assertStringNotContainsString('963991234567', json_encode($payload));
    }

    public function test_an_owner_may_only_subscribe_to_their_own_place_channel(): void
    {
        // The suite broadcasts over the null driver so tests never hit the
        // network, but null's auth() is a no-op and would pass anything.
        // Swap in a real broadcaster and re-register the channel callbacks
        // onto it, so this exercises authorisation for real.
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => '127.0.0.1', 'port' => 8081, 'scheme' => 'http', 'useTLS' => false],
        ]);
        require base_path('routes/channels.php');

        $owner = User::factory()->create();
        $place = Place::factory()->for($owner)->create();
        $stranger = User::factory()->create();

        $this->actingAs($owner)
            ->post('/broadcasting/auth', ['channel_name' => "private-place.{$place->id}", 'socket_id' => '123.456'])
            ->assertOk();

        $this->actingAs($stranger)
            ->post('/broadcasting/auth', ['channel_name' => "private-place.{$place->id}", 'socket_id' => '123.456'])
            ->assertForbidden();
    }
}
