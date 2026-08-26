<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public shell: legal pages, error pages and the head-level tags that
 * decide how a shared link looks in WhatsApp.
 */
class PublicSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_legal_pages_render_in_both_locales(): void
    {
        foreach (['privacy', 'terms'] as $doc) {
            foreach (['ar', 'en'] as $locale) {
                $this->get("/{$doc}?lang={$locale}")
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page
                        ->component('public/legal')
                        ->where('document', $doc)
                        ->has('legal.'.$doc, 5)
                    );
            }
        }
    }

    public function test_an_unknown_page_renders_the_branded_404(): void
    {
        $response = $this->get('/no-such-place/no-such-event');

        $response->assertNotFound();
        // The branded page, not Laravel's default.
        $response->assertSee('404', false);
        $response->assertDontSee('Whoops', false);
    }

    public function test_error_pages_are_not_indexable(): void
    {
        $this->get('/no-such-place/no-such-event')
            ->assertSee('name="robots" content="noindex"', false);
    }

    /**
     * These links are shared in WhatsApp far more than they are searched for,
     * so the preview card is the first impression.
     */
    public function test_pages_carry_social_sharing_tags(): void
    {
        $event = Event::factory()->create();

        $this->get(route('events.show', [$event->place, $event]))
            ->assertSee('property="og:image"', false)
            ->assertSee('property="og:site_name"', false)
            ->assertSee('name="twitter:card"', false);
    }

    public function test_every_page_offers_a_skip_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('skip-to-content', false)
            ->assertSee('#main-content', false);
    }

    public function test_the_sitemap_never_lists_a_url_robots_forbids(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        preg_match_all('/<loc>([^<]+)<\/loc>/', (string) $sitemap, $locs);
        preg_match_all('/^Disallow: (.+)$/m', (string) $robots, $disallowed);

        foreach ($locs[1] as $loc) {
            $path = parse_url(html_entity_decode($loc), PHP_URL_PATH) ?: '/';

            foreach ($disallowed[1] as $rule) {
                $rule = trim($rule);

                $this->assertFalse(
                    $rule !== '/' && str_starts_with($path, $rule),
                    "Sitemap lists {$path}, which robots.txt disallows via {$rule}."
                );
            }
        }
    }

    public function test_the_venue_picker_is_allowed_to_read_the_owners_location(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($owner)->create();

        // A feature omitted from Permissions-Policy is allowed, and one denied
        // there cannot be re-enabled by prompting. Denying geolocation here
        // made "my location" fail silently on every device.
        $policy = $this->actingAs($owner)
            ->get('/owner/place')
            ->assertOk()
            ->headers->get('Permissions-Policy');

        $this->assertStringContainsString('geolocation=(self)', (string) $policy);
        $this->assertStringContainsString('camera=()', (string) $policy);
    }

    public function test_the_scanner_is_allowed_the_camera_and_nothing_else(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($owner)->create();

        $policy = $this->actingAs($owner)
            ->get('/owner/scan')
            ->assertOk()
            ->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', (string) $policy);
        $this->assertStringContainsString('geolocation=()', (string) $policy);
    }

    public function test_ordinary_pages_are_denied_every_powerful_feature(): void
    {
        $policy = (string) $this->get('/')->assertOk()->headers->get('Permissions-Policy');

        foreach (['camera', 'geolocation', 'microphone', 'payment'] as $feature) {
            $this->assertStringContainsString("{$feature}=()", $policy);
        }
    }

    public function test_every_route_that_uses_a_device_feature_is_granted_it(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($owner)->create();

        // Each entry is a route that renders a component calling the matching
        // browser API. A feature denied in this header cannot be re-enabled by
        // prompting, so a missing grant is a silent, unfixable failure.
        $grants = [
            '/owner/scan' => 'camera',
            '/owner/place' => 'geolocation',
        ];

        foreach ($grants as $path => $feature) {
            $policy = (string) $this->actingAs($owner)
                ->get($path)
                ->assertOk()
                ->headers->get('Permissions-Policy');

            $this->assertStringContainsString(
                "{$feature}=(self)",
                $policy,
                "{$path} uses {$feature} but the header does not grant it."
            );

            // And nothing beyond what that page actually needs.
            foreach (array_diff(['camera', 'geolocation'], [$feature]) as $other) {
                $this->assertStringContainsString("{$other}=()", $policy);
            }
        }
    }

    public function test_the_favicon_is_the_brand_mark_not_laravel(): void
    {
        $svg = (string) file_get_contents(public_path('favicon.svg'));

        // The Pass, in brand colours: jade body, saffron admitted dot. Pinning
        // the palette rather than one hex would have caught nothing when the
        // brand changed -- this asserts both halves of the current mark.
        $this->assertStringContainsString('#0A5C49', $svg);
        $this->assertStringContainsString('#E8A72B', $svg);

        // Laravel's mark uses a 166-unit viewBox.
        $this->assertStringNotContainsString('viewBox="0 0 166 166"', $svg);
    }

    public function test_every_shipped_icon_exists_and_is_the_right_size(): void
    {
        // These are generated by `npm run icons` from resources/brand. A
        // rebrand that updates the sources but not the rasters ships a stale
        // icon to every installed home screen, which nothing else would catch.
        $expected = [
            'icons/icon-192.png' => 192,
            'icons/icon-512.png' => 512,
            'icons/icon-maskable-512.png' => 512,
            'apple-touch-icon.png' => 180,
        ];

        foreach ($expected as $path => $size) {
            $this->assertFileExists(public_path($path));

            [$width, $height] = getimagesize(public_path($path));

            $this->assertSame($size, $width, "{$path} width");
            $this->assertSame($size, $height, "{$path} height");
        }
    }
}
