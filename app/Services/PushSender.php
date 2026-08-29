<?php

namespace App\Services;

use App\Models\EventWatcher;
use App\Models\Ticket;
use App\Support\NotificationCopy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends pushes through Firebase Cloud Messaging.
 *
 * Unconfigured is a supported state: with no credentials the sender reports
 * that it is disabled and does nothing. Nothing in the booking or verification
 * flow depends on a push being delivered.
 *
 * Wording never lives here -- every body comes from NotificationCopy, which
 * rotates between variants so repeat bookers are not read the same sentence
 * every time.
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
        return $this->toTicket($ticket, 'status.'.$ticket->status->value);
    }

    /**
     * The hold is about to lapse. Sent once per ticket by `tickets:remind`.
     */
    public function holdReminder(Ticket $ticket): int
    {
        return $this->toTicket($ticket, 'reminder', function (string $locale) use ($ticket): array {
            $deadline = $ticket->hold_expires_at;

            return ['time' => $deadline === null
                ? ''
                // ar-SY gives Levantine month names and Latin digits, which
                // is the one numeral rule the whole product follows.
                // settings(), not locale(): the latter is a getter/setter
                // overload that PHPStan sees as possibly returning a string.
                : $deadline->settings(['locale' => $locale === 'ar' ? 'ar-SY' : 'en-GB'])
                    ->isoFormat('D MMM, HH:mm')];
        });
    }

    /**
     * A seat came back on an event somebody is waiting for.
     */
    public function seatFreed(EventWatcher $watcher): int
    {
        if (! $this->isConfigured() || blank($watcher->fcm_token)) {
            return 0;
        }

        $event = $watcher->event;
        $locale = $watcher->locale;

        $body = NotificationCopy::pick('seat_freed', $locale, [
            'event' => $event->title($locale),
        ], 'watcher:'.$watcher->getKey());

        $accepted = $this->deliver(
            (string) $watcher->fcm_token,
            $event->place->name($locale),
            $body,
            route('events.show', ['place' => $event->place->slug, 'event' => $event->slug]),
        );

        if ($accepted === false) {
            $watcher->forceFill(['fcm_token' => null])->save();

            return 0;
        }

        return $accepted ? 1 : 0;
    }

    /**
     * Sends one message kind to every device registered against a ticket.
     *
     * The title is always the event, so the notification tray shows what this
     * is about; only the body rotates.
     *
     * @param  (callable(string): array<string, string|int>)|null  $replace
     *                                                                       Placeholder values, resolved per device locale.
     */
    private function toTicket(Ticket $ticket, string $kind, ?callable $replace = null): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $sent = 0;

        foreach ($ticket->pushSubscriptions as $subscription) {
            $locale = $subscription->locale;

            $body = NotificationCopy::pick(
                $kind,
                $locale,
                $replace === null ? [] : $replace($locale),
                'ticket:'.$ticket->getKey(),
            );

            $accepted = $this->deliver(
                $subscription->fcm_token,
                $ticket->event->title($locale),
                $body,
                route('tickets.show', $ticket),
            );

            if ($accepted === false) {
                // A 404 or 410 means the device token is dead; stop using it.
                $subscription->delete();

                continue;
            }

            if ($accepted) {
                $subscription->forceFill(['last_used_at' => now()])->save();
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return bool|null true accepted, false the token is dead and should be
     *                   discarded, null a transient failure worth keeping the
     *                   token for.
     */
    private function deliver(string $token, string $title, string $body, string $link): ?bool
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->post($this->endpoint(), [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'webpush' => ['fcm_options' => ['link' => $link]],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            // 404 and 410 are FCM saying the device is gone.
            if (in_array($response->status(), [404, 410], true)) {
                return false;
            }

            /*
             * A 400 is ambiguous: FCM says INVALID_ARGUMENT both for a message
             * we built wrong and for a registration token that is simply not
             * one. Only the second is the subscription's fault, and it will
             * never become valid -- so it is dropped, while a 400 about
             * anything else keeps the token rather than deleting real
             * subscriptions over a bug of ours.
             */
            if ($response->status() === 400
                && str_contains((string) $response->json('error.message'), 'registration token')) {
                return false;
            }

            return null;
        } catch (Throwable $e) {
            // Push is best-effort: never let it break the request that
            // triggered it.
            Log::warning('FCM push failed', ['error' => $e->getMessage()]);

            return null;
        }
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
