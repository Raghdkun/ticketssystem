<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Light, dark, or the device's choice.
 *
 * Nobody who has not picked a side should be handed one: the default is
 * "system", and the first paint follows the device rather than the cookie.
 */
class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_visit_follows_the_device(): void
    {
        Place::factory()->create(['is_active' => true]);

        $response = $this->get('/');

        // The inline script decides from prefers-color-scheme when the
        // appearance is "system"; a hard "light" here would ignore the device.
        $response->assertSee("const appearance = 'system'", false);
        $response->assertDontSee('<html lang="ar" dir="rtl" class="dark"', false);
    }

    public function test_a_chosen_appearance_is_honoured_before_javascript_runs(): void
    {
        Place::factory()->create(['is_active' => true]);

        $this->withUnencryptedCookie('appearance', 'dark')
            ->get('/')
            ->assertSee('class="dark"', false);
    }

    public function test_the_toggle_sits_beside_the_language_switch_on_public_pages(): void
    {
        // The two controls travel together: every page that offers one offers
        // the other, and the dashboard header carries the same pair.
        foreach ([
            'resources/js/pages/welcome.tsx',
            'resources/js/pages/public/event.tsx',
            'resources/js/pages/public/ticket.tsx',
            'resources/js/pages/public/place.tsx',
            'resources/js/pages/public/my-tickets.tsx',
            'resources/js/pages/public/invitation.tsx',
            'resources/js/layouts/auth/auth-simple-layout.tsx',
            'resources/js/components/app-sidebar-header.tsx',
        ] as $file) {
            $source = (string) file_get_contents(base_path($file));

            $this->assertStringContainsString('<LanguageToggle', $source, $file);
            $this->assertStringContainsString('<ThemeToggle', $source, $file);
        }
    }

    public function test_the_hook_defaults_to_system_not_light(): void
    {
        $hook = (string) file_get_contents(resource_path('js/hooks/use-appearance.tsx'));

        $this->assertStringNotContainsString("|| 'light'", $hook);
        $this->assertStringContainsString("|| 'system'", $hook);
    }
}
