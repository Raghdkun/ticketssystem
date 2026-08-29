<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends ticket status pushes through Firebase Cloud Messaging.
 *
 * Unconfigured is a supported state: with no credentials the sender reports
 * that it is disabled and does nothing. Nothing in the booking or verification
 * flow depends on a push being delivered.
 */
final class PushSender
{
    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id'))
            && filled(config('services.fcm.credentials'));
    }

    /**
     * @return int Number of devices the notification was accepted for.
     */
    public function ticketStatusChanged(Ticket $ticket): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $sent = 0;

        foreach ($ticket->pushSubscriptions as $subscription) {
            $body = __('push.status.'.$ticket->status->value, [], $subscription->locale);

            try {
                $response = Http::withToken($this->accessToken())
                    ->post($this->endpoint(), [
                        'message' => [
                            'token' => $subscription->fcm_token,
                            'notification' => [
                                'title' => $ticket->event->title($subscription->locale),
                                'body' => $body,
                            ],
                            'webpush' => [
                                'fcm_options' => ['link' => route('tickets.show', $ticket)],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                    $sent++;

                    continue;
                }

                // A 404 or 410 means the device token is dead; stop using it.
                if (in_array($response->status(), [404, 410], true)) {
                    $subscription->delete();
                }
            } catch (Throwable $e) {
                // Push is best-effort: never let it break the request that
                // triggered it.
                Log::warning('FCM push failed', ['ticket' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            config('services.fcm.project_id')
        );
    }

    /**
     * A bearer token for the FCM v1 API.
     *
     * FCM v1 takes an OAuth access token, and those last an hour, so they
     * cannot live in the environment -- a value pasted into `.env` is stale
     * before the next event. `FCM_ACCESS_TOKEN` is honoured if set, which is
     * useful for a one-off test, but the real path signs a JWT with the
     * service account's own key and exchanges it with Google.
     */
    private function accessToken(): string
    {
        $manual = config('services.fcm.access_token');

        if (filled($manual)) {
            return (string) $manual;
        }

        // Cached just short of the hour Google grants, so a token is never
        // used in the seconds after it expires.
        return Cache::remember(
            'fcm.access_token',
            now()->addMinutes(55),
            fn (): string => $this->exchangeServiceAccount()
        );
    }

    /**
     * Signs a JWT with the service account key and trades it for a token.
     *
     * Implemented directly rather than pulling in google/apiclient: this is
     * one signature and one request, against a dependency that would bring a
     * great deal else with it.
     */
    private function exchangeServiceAccount(): string
    {
        $path = (string) config('services.fcm.credentials');

        if (! is_file($path)) {
            throw new RuntimeException("FCM_CREDENTIALS does not point at a readable file: {$path}");
        }

        /** @var array{client_email?: string, private_key?: string, token_uri?: string} $account */
        $account = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (blank($account['client_email'] ?? null) || blank($account['private_key'] ?? null)) {
            throw new RuntimeException('FCM_CREDENTIALS is not a Firebase service-account key.');
        }

        $tokenUri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();

        $claims = [
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $encode = fn (array $part): string => rtrim(strtr(
            base64_encode(json_encode($part, JSON_THROW_ON_ERROR)), '+/', '-_'
        ), '=');

        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        $signature = '';

        if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM assertion with the service-account key.');
        }

        $assertion = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $response = Http::asForm()->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new RuntimeException('Google refused the FCM assertion: '.$response->body());
        }

        return (string) $response->json('access_token');
    }
}
