<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Support\PosterPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The poster prompt is region-neutral by default.
 *
 * Place comes from the event's own venue and location. The old build
 * anchored every poster to As-Suwayda unconditionally; that grounding is
 * still available, as a mood the owner chooses, and nowhere else.
 */
class PosterPromptTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $place = Place::factory()->for(User::factory())->create([
            'name_en' => 'Qanawat Theatre',
            'name_ar' => 'مسرح قنوات',
        ]);

        return Event::factory()->for($place)->create([
            'title_en' => 'Dabke Night',
            'title_ar' => 'ليلة دبكة',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function choices(array $overrides = []): array
    {
        return [
            'kind' => 'folk',
            'mood' => 'elegant',
            'style' => 'screenprint',
            'palette' => 'brand',
            'format' => 'poster',
            ...$overrides,
        ];
    }

    public function test_a_default_prompt_is_not_anchored_to_any_region(): void
    {
        $event = $this->event();

        $en = PosterPrompt::build($event, $this->choices(), 'en')['prompt'];
        $ar = PosterPrompt::build($event, $this->choices(), 'ar')['prompt'];

        $this->assertStringNotContainsString('Suwayda', $en);
        $this->assertStringNotContainsString('Jabal al-Arab', $en);
        $this->assertStringNotContainsString('السويداء', $ar);
        $this->assertStringNotContainsString('جبل العرب', $ar);

        // The venue still grounds the artwork.
        $this->assertStringContainsString('Qanawat Theatre', $en);
        $this->assertStringContainsString('مسرح قنوات', $ar);
    }

    public function test_the_heritage_mood_opts_into_the_regional_grounding(): void
    {
        $event = $this->event();

        $en = PosterPrompt::build($event, $this->choices(['mood' => 'heritage']), 'en')['prompt'];
        $ar = PosterPrompt::build($event, $this->choices(['mood' => 'heritage']), 'ar')['prompt'];

        $this->assertStringContainsString('Jabal al-Arab', $en);
        $this->assertStringContainsString('جبل العرب', $ar);
    }

    public function test_the_brand_palette_is_the_new_brand(): void
    {
        $prompt = PosterPrompt::build($this->event(), $this->choices(), 'en')['prompt'];

        $this->assertStringContainsString('#F66002', $prompt);
        $this->assertStringContainsString('#0D0E0F', $prompt);
        $this->assertStringNotContainsString('#0A5C49', $prompt);
    }

    public function test_an_owner_can_still_ask_for_the_heritage_mood(): void
    {
        $event = $this->event();

        $this->actingAs($event->place->user)
            ->post(route('owner.events.poster.prompt', $event), [...$this->choices(['mood' => 'heritage']), 'locale' => 'en'])
            ->assertSuccessful();
    }
}
