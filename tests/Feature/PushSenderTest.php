<?php

namespace Tests\Feature;

use App\Actions\VerifyTicket;
use App\Models\Event;
use App\Models\PushSubscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The token exchange, which is what stands between a service-account key and
 * a notification actually arriving.
 */
class PushSenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Writes a throwaway service-account key with a real RSA pair, so the
     * signature is genuinely produced rather than mocked around.
     */
    private function serviceAccount(): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($key, $private);

        $path = tempnam(sys_get_temp_dir(), 'fcm').'.json';
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'swaida-tickets',
            'client_email' => 'push@swaida-tickets.iam.gserviceaccount.com',
            'private_key' => $private,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        return $path;
    }

    public function test_it_is_unconfigured_without_credentials(): void
    {
        config(['services.fcm.project_id' => 'swaida-tickets', 'services.fcm.credentials' => null]);

        $this->assertFalse(app(PushSender::class)->isConfigured());
    }

    public function test_a_service_account_key_is_exchanged_for_a_token(): void
    {
        $path = $this->serviceAccount();

        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => $path,
            'services.fcm.access_token' => null,
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.fresh', 'expires_in' => 3599]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/x/messages/1']),
        ]);

        $token = (new \ReflectionMethod(PushSender::class, 'accessToken'));
        $token->setAccessible(true);

        $this->assertSame('ya29.fresh', $token->invoke(app(PushSender::class)));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
                return false;
            }

            // A signed JWT bearer assertion, not the raw key.
            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && substr_count((string) $request['assertion'], '.') === 2;
        });

        @unlink($path);
    }

    public function test_a_manual_token_short_circuits_the_exchange(): void
    {
        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => '/nonexistent.json',
            'services.fcm.access_token' => 'ya29.manual',
        ]);

        Http::fake();

        $token = new \ReflectionMethod(PushSender::class, 'accessToken');
        $token->setAccessible(true);

        $this->assertSame('ya29.manual', $token->invoke(app(PushSender::class)));

        Http::assertNothingSent();
    }

    public function test_a_missing_key_file_says_so(): void
    {
        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => '/definitely/not/here.json',
            'services.fcm.access_token' => null,
        ]);

        $this->expectExceptionMessageMatches('/does not point at a readable file/');

        $token = new \ReflectionMethod(PushSender::class, 'accessToken');
        $token->setAccessible(true);
        $token->invoke(app(PushSender::class));
    }

    /**
     * A status change must reach each device exactly once.
     *
     * Both listeners live in app/Listeners, which Laravel discovers on its
     * own, and they were *also* registered by hand in AppServiceProvider --
     * so every ticket status push went out twice, to every device.
     */
    public function test_a_status_change_pushes_once_per_device(): void
    {
        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => __FILE__,
            'services.fcm.access_token' => 'test-token',
        ]);

        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

        $ticket = Ticket::factory()
            ->for(Event::factory())
            ->create();
        $ticket->pushSubscriptions()->create(['fcm_token' => 'device-1', 'locale' => 'ar']);

        app(VerifyTicket::class)->markPaid($ticket, User::factory()->create());

        Http::assertSentCount(1);
    }

    /**
     * @return array{0: Ticket, 1: PushSubscription}
     */
    private function subscribedTicket(): array
    {
        config([
            'services.fcm.project_id' => 'swaida-tickets',
            'services.fcm.credentials' => __FILE__,
            'services.fcm.access_token' => 'test-token',
        ]);

        $ticket = Ticket::factory()->for(Event::factory())->create();
        $subscription = $ticket->pushSubscriptions()->create([
            'fcm_token' => 'device-1',
            'locale' => 'ar',
        ]);

        return [$ticket, $subscription];
    }

    /**
     * FCM rejects a malformed device token with a 400, not a 404 -- verified
     * against the live API. Kept, it would be retried on every status change
     * for the life of the booking.
     */
    public function test_a_token_fcm_calls_invalid_is_discarded(): void
    {
        [$ticket, $subscription] = $this->subscribedTicket();

        Http::fake(['fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'The registration token is not a valid FCM registration token',
            ],
        ], 400)]);

        app(PushSender::class)->ticketStatusChanged($ticket);

        $this->assertModelMissing($subscription);
    }

    /**
     * A 400 about anything else is our own bug, and deleting every real
     * subscription over it would be unrecoverable.
     */
    public function test_a_400_about_our_own_payload_keeps_the_subscription(): void
    {
        [$ticket, $subscription] = $this->subscribedTicket();

        Http::fake(['fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'message' => 'Invalid JSON payload received. Unknown name "notifcation".',
            ],
        ], 400)]);

        app(PushSender::class)->ticketStatusChanged($ticket);

        $this->assertModelExists($subscription);
    }

    public function test_a_dead_device_is_discarded(): void
    {
        [$ticket, $subscription] = $this->subscribedTicket();

        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404)]);

        app(PushSender::class)->ticketStatusChanged($ticket);

        $this->assertModelMissing($subscription);
    }

    /**
     * A five hundred is FCM having a bad minute, not a verdict on the device.
     */
    public function test_a_server_error_keeps_the_subscription(): void
    {
        [$ticket, $subscription] = $this->subscribedTicket();

        Http::fake(['fcm.googleapis.com/*' => Http::response([], 503)]);

        app(PushSender::class)->ticketStatusChanged($ticket);

        $this->assertModelExists($subscription);
    }
}
