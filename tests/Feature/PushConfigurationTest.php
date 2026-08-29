<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The background messaging worker is plain, un-bundled JavaScript served from
 * the origin root, so it cannot read Vite env vars and its Firebase config is
 * inlined by hand. That makes it the one place config can silently drift.
 */
class PushConfigurationTest extends TestCase
{
    private function worker(): string
    {
        $path = public_path('firebase-messaging-sw.js');

        $this->assertFileExists($path, 'The background messaging worker is missing.');

        return (string) file_get_contents($path);
    }

    public function test_the_worker_targets_the_same_firebase_project_as_the_server(): void
    {
        $projectId = config('services.fcm.project_id');

        if (blank($projectId)) {
            $this->markTestSkipped('FCM is not configured in this environment.');
        }

        // A worker pointed at a different project registers tokens the server
        // can never send to. Foreground messages would still work, so this
        // fails in the least visible way possible.
        $this->assertMatchesRegularExpression(
            '/projectId\s*:\s*[\'"]'.preg_quote((string) $projectId, '/').'[\'"]/',
            $this->worker(),
            'firebase-messaging-sw.js names a different Firebase project than FCM_PROJECT_ID.'
        );
    }

    public function test_the_vapid_key_is_a_well_formed_public_key(): void
    {
        $key = config('services.fcm.vapid_key');

        if (blank($key)) {
            $this->markTestSkipped('No VAPID key configured in this environment.');
        }

        $raw = base64_decode(strtr((string) $key, '-_', '+/'), true);

        // A Web Push application server key is an uncompressed P-256 point:
        // 65 bytes beginning with 0x04. A truncated or mistyped key decodes to
        // something else and only fails later, inside getToken.
        $this->assertNotFalse($raw, 'The VAPID key is not valid base64url.');
        $this->assertSame(65, strlen((string) $raw), 'A VAPID public key is 65 bytes.');
        $this->assertSame(0x04, ord((string) $raw), 'A VAPID public key starts with 0x04.');
    }

    public function test_the_worker_sits_at_the_origin_root(): void
    {
        // The web server serves this as a static file, so its location on disk
        // is the thing to assert -- a worker nested any deeper gets a scope
        // that cannot cover the app, and background messages stop arriving.
        $this->assertFileExists(public_path('firebase-messaging-sw.js'));

        // It must stay a classic worker. `importScripts` is only available
        // outside module workers, so its presence is proof the file was not
        // converted to ES modules -- which would break registration.
        $this->assertStringContainsString('importScripts(', $this->worker());
    }
}
