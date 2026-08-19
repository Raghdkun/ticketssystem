<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Exchange the service-account credentials for a short-lived OAuth token.
     * Left as a single seam so swapping in google/apiclient later is a
     * one-method change.
     */
    private function accessToken(): string
    {
        return (string) config('services.fcm.access_token');
    }
}
