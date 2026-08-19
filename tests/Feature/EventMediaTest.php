<?php

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\MediaLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->owner = User::factory()->create();
        $this->event = Event::factory()->for(Place::factory()->for($this->owner))->create();
    }

    public function test_an_owner_can_add_a_photo(): void
    {
        $this->actingAs($this->owner)
            ->post(route('owner.events.media.store', $this->event), [
                'kind' => 'image',
                'file' => UploadedFile::fake()->image('poster.jpg', 800, 600),
            ])
            ->assertRedirect();

        $media = $this->event->media()->sole();

        $this->assertSame(MediaType::Image, $media->type);
        // Re-encoded to WebP, which normalises the file and drops anything
        // embedded in the original.
        $this->assertSame('image/webp', $media->mime);
        Storage::disk('public')->assertExists($media->path);
    }

    /**
     * A file whose bytes are not a decodable image must fail validation, not
     * surface a 500 from the image decoder.
     */
    public function test_a_file_disguised_as_an_image_is_rejected_gracefully(): void
    {
        $this->actingAs($this->owner)
            ->post(route('owner.events.media.store', $this->event), [
                'kind' => 'image',
                'file' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwned";'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $this->event->media()->count());
    }

    public function test_an_svg_is_not_an_accepted_image(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->actingAs($this->owner)
            ->post(route('owner.events.media.store', $this->event), [
                'kind' => 'image',
                'file' => UploadedFile::fake()->createWithContent('x.svg', $svg),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_video_larger_than_the_cap_is_rejected(): void
    {
        $tooBig = UploadedFile::fake()->create('huge.mp4', MediaLibrary::VIDEO_MAX_KB + 1024, 'video/mp4');

        $this->actingAs($this->owner)
            ->post(route('owner.events.media.store', $this->event), [
                'kind' => 'video',
                'file' => $tooBig,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $this->event->media()->count());
    }

    public function test_the_video_cap_is_one_hundred_megabytes(): void
    {
        $this->assertSame(102400, MediaLibrary::VIDEO_MAX_KB);
    }

    public function test_a_video_can_be_promoted_and_demoted(): void
    {
        $media = $this->event->media()->create([
            'type' => MediaType::Video,
            'path' => 'events/1/media/clip.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 1024,
        ]);

        $this->actingAs($this->owner)
            ->post(route('owner.events.media.promo', [$this->event, $media]));
        $this->assertSame($media->id, $this->event->fresh()->promo_video_id);

        // Posting again clears it, so the same control toggles.
        $this->actingAs($this->owner)
            ->post(route('owner.events.media.promo', [$this->event, $media]));
        $this->assertNull($this->event->fresh()->promo_video_id);
    }

    public function test_an_image_cannot_be_used_as_the_promo_video(): void
    {
        $media = $this->event->media()->create([
            'type' => MediaType::Image,
            'path' => 'events/1/media/photo.webp',
            'mime' => 'image/webp',
            'size_bytes' => 512,
        ]);

        $this->actingAs($this->owner)
            ->post(route('owner.events.media.promo', [$this->event, $media]))
            ->assertStatus(422);
    }

    public function test_deleting_the_promo_video_clears_the_selection(): void
    {
        $media = $this->event->media()->create([
            'type' => MediaType::Video,
            'path' => 'events/1/media/clip.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 1024,
        ]);
        $this->event->promo_video_id = $media->id;
        $this->event->save();

        $this->actingAs($this->owner)
            ->delete(route('owner.events.media.destroy', [$this->event, $media]))
            ->assertRedirect();

        $this->assertNull($this->event->fresh()->promo_video_id);
        $this->assertSame(0, $this->event->media()->count());
    }

    public function test_a_stranger_cannot_add_media_to_someone_elses_event(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('owner.events.media.store', $this->event), [
                'kind' => 'image',
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->event->media()->count());
    }

    public function test_media_from_another_event_cannot_be_targeted(): void
    {
        $other = Event::factory()->for(Place::factory()->for($this->owner))->create();
        $media = $other->media()->create([
            'type' => MediaType::Video,
            'path' => 'events/2/media/clip.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 1024,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('owner.events.media.destroy', [$this->event, $media]))
            ->assertNotFound();
    }

    public function test_perks_are_saved_with_the_event(): void
    {
        $this->actingAs($this->owner)->put(route('owner.events.update', $this->event), [
            'title_ar' => 'حفل', 'title_en' => 'Gig',
            'price' => 0, 'currency' => 'SYP',
            'total_quantity' => 50, 'max_per_appointment' => 4, 'hold_hours' => 24,
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'appointments_close_at' => now()->addDays(6)->toDateTimeString(),
            'status' => 'published', 'theme_mode' => 'auto',
            'perks' => [
                ['body_en' => 'One free drink', 'body_ar' => 'مشروب مجاني'],
                ['body_en' => 'Reserved seat', 'body_ar' => 'مقعد محجوز'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $this->event->perks()->count());
        $this->assertSame('One free drink', $this->event->perks()->first()->body_en);
    }
}
