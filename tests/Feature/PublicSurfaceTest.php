<?php

namespace Tests\Feature;

use App\Models\Event;
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

    public function test_the_favicon_is_the_brand_mark_not_laravel(): void
    {
        $svg = (string) file_get_contents(public_path('favicon.svg'));

        $this->assertStringContainsString('4f46e5', $svg);
        // Laravel's mark uses a 166-unit viewBox.
        $this->assertStringNotContainsString('viewBox="0 0 166 166"', $svg);
    }
}
