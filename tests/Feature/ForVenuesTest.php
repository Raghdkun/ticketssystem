<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The page for a venue that is not a partner yet: the pitch and the number
 * both come from settings, and the page never becomes a way to register.
 */
class ForVenuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_with_the_shipped_pitch_and_no_number(): void
    {
        $this->get('/for-venues')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/for-venues')
                ->where('pitch', Settings::DEFAULTS['venues_pitch_ar'])
                ->where('whatsapp', null));
    }

    public function test_the_pitch_and_number_come_from_settings_in_the_page_language(): void
    {
        app(Settings::class)->put([
            'venues_pitch_en' => 'Bring your hall to Nas.',
            'venues_pitch_ar' => 'اجلب قاعتك إلى ناس.',
            'support_whatsapp' => '0991234567',
        ]);

        $this->get('/for-venues?lang=en')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pitch', 'Bring your hall to Nas.')
                ->where('whatsapp', '0991234567'));

        $this->get('/for-venues?lang=ar')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pitch', 'اجلب قاعتك إلى ناس.'));
    }

    public function test_an_administrator_edits_the_pitch_from_the_dashboard(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post('/admin/settings', [
                'app_name_en' => 'Nas',
                'app_name_ar' => 'ناس',
                'venues_pitch_en' => 'New pitch.',
                'venues_pitch_ar' => 'نص جديد.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('New pitch.', app(Settings::class)->get('venues_pitch_en'));
    }

    public function test_the_segment_is_reserved_so_a_venue_cannot_shadow_it(): void
    {
        // A venue whose slug happened to be "for-venues" must not answer here.
        Place::factory()->create(['slug' => 'for-venues']);

        $this->get('/for-venues')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('public/for-venues'));
    }

    public function test_it_is_in_the_sitemap_and_linked_from_the_login_page(): void
    {
        $this->get('/sitemap.xml')->assertSee(route('for_venues'), false);
        $this->get('/login')->assertOk();
    }
}
