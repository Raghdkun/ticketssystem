<?php

namespace Tests\Feature;

use App\Services\PushSender;
use Tests\TestCase;

/**
 * The Firebase background worker is plain JavaScript served from the origin
 * root: it cannot import from the app bundle or read Vite env vars, so its
 * config is inlined and can drift from .env without anything failing loudly.
 * Push would simply stop arriving. These tests tie the two together.
 */
class FirebaseMessagingWorkerTest extends TestCase
{
    private function worker(): string
    {
        return (string) file_get_contents(public_path('firebase-messaging-sw.js'));
    }

    public function test_the_worker_is_served_from_the_origin_root(): void
    {
        // Firebase only looks for it here; a nested path is never fetched.
        $this->assertFileExists(public_path('firebase-messaging-sw.js'));
    }

    public function test_the_worker_handles_background_messages(): void
    {
        $worker = $this->worker();

        $this->assertStringContainsString('onBackgroundMessage', $worker);
        $this->assertStringContainsString('showNotification', $worker);
        // Tapping a notification must lead back to the ticket.
        $this->assertStringContainsString('notificationclick', $worker);
    }

    /**
     * The inlined project must be the same one the app registers against,
     * otherwise tokens are minted for one project and sent from another.
     */
    public function test_the_worker_config_matches_the_application_project(): void
    {
        $worker = $this->worker();
        $projectId = config('services.fcm.project_id');

        $this->assertNotEmpty($projectId, 'FCM_PROJECT_ID is not configured.');
        $this->assertStringContainsString((string) $projectId, $worker);
    }

    public function test_the_worker_references_an_icon_that_exists(): void
    {
        preg_match_all("/icon:\s*'([^']+)'|badge:\s*'([^']+)'/", $this->worker(), $matches);

        $icons = array_filter(array_merge($matches[1], $matches[2]));

        $this->assertNotEmpty($icons);

        foreach ($icons as $icon) {
            $this->assertFileExists(public_path(ltrim($icon, '/')));
        }
    }

    /**
     * Sending requires a service-account key. Until one is present the sender
     * must stay inert rather than half-working.
     */
    public function test_sending_stays_disabled_until_credentials_are_supplied(): void
    {
        if (filled(config('services.fcm.credentials'))) {
            $this->markTestSkipped('Credentials are configured; the enabled path is covered elsewhere.');
        }

        $this->assertFalse(app(PushSender::class)->isConfigured());
    }
}
