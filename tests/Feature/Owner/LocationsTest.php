<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Location;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($owner)->create();

        return $owner;
    }

    public function test_an_owner_can_add_several_locations(): void
    {
        $owner = $this->owner();

        foreach (['Main Hall', 'Rooftop'] as $name) {
            $this->actingAs($owner)
                ->post('/owner/locations', [
                    'name_ar' => 'قاعة '.$name,
                    'name_en' => $name,
                    'latitude' => 32.7093878,
                    'longitude' => 36.5687496,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $owner->places()->first()->locations()->count());
    }

    public function test_the_first_location_becomes_the_default_on_its_own(): void
    {
        $owner = $this->owner();

        // A venue with locations but no default would leave every event
        // resolving to nothing.
        $this->actingAs($owner)->post('/owner/locations', [
            'name_ar' => 'قاعة', 'name_en' => 'Hall',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($owner->places()->first()->locations()->first()->is_primary);
    }

    public function test_only_one_location_is_ever_the_default(): void
    {
        $owner = $this->owner();
        $place = $owner->places()->first();

        $first = Location::factory()->for($place)->primary()->create();
        $second = Location::factory()->for($place)->create();

        $this->actingAs($owner)->patch("/owner/locations/{$second->id}", [
            'name_ar' => $second->name_ar,
            'name_en' => $second->name_en,
            'is_primary' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($first->fresh()->is_primary);
    }

    public function test_half_a_coordinate_is_rejected(): void
    {
        // Longitude without latitude silently becomes (0, lng) -- a point in
        // the Gulf of Guinea -- if the pair is not validated together.
        $this->actingAs($this->owner())
            ->post('/owner/locations', [
                'name_ar' => 'قاعة', 'name_en' => 'Hall',
                'longitude' => 36.5687496,
            ])
            ->assertSessionHasErrors('latitude');
    }

    public function test_deleting_the_default_promotes_another(): void
    {
        $owner = $this->owner();
        $place = $owner->places()->first();

        $primary = Location::factory()->for($place)->primary()->create();
        $other = Location::factory()->for($place)->create(['sort' => 1]);

        $this->actingAs($owner)->delete("/owner/locations/{$primary->id}");

        $this->assertTrue($other->fresh()->is_primary);
    }

    public function test_deleting_a_location_leaves_its_events_standing(): void
    {
        $owner = $this->owner();
        $place = $owner->places()->first();

        $fallback = Location::factory()->for($place)->primary()->create();
        $doomed = Location::factory()->for($place)->create(['sort' => 1]);
        $event = Event::factory()->for($place)->create(['location_id' => $doomed->id]);

        $this->actingAs($owner)->delete("/owner/locations/{$doomed->id}");

        // The foreign key nulls out and the event falls back to the default.
        $this->assertNull($event->fresh()->location_id);
        $this->assertSame($fallback->id, $event->fresh()->resolvedLocation()?->id);
    }

    public function test_an_event_cannot_borrow_another_venues_location(): void
    {
        $owner = $this->owner();
        $stranger = Location::factory()->create();

        $this->actingAs($owner)
            ->post('/owner/events', [
                'title_ar' => 'حفل', 'title_en' => 'Concert',
                'description_ar' => 'وصف', 'description_en' => 'Description',
                'price' => 1000, 'currency' => 'SYP',
                'total_quantity' => 50, 'max_per_appointment' => 4, 'hold_hours' => 24,
                'starts_at' => now()->addMonth()->toDateTimeString(),
                'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
                'status' => EventStatus::Draft->value,
                'location_id' => $stranger->id,
            ])
            ->assertSessionHasErrors('location_id');
    }

    public function test_another_owner_cannot_edit_a_location(): void
    {
        $location = Location::factory()->for($this->owner()->places()->first())->create();

        $intruder = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($intruder)->create();

        $this->actingAs($intruder)
            ->patch("/owner/locations/{$location->id}", [
                'name_ar' => 'مختطف', 'name_en' => 'Hijacked',
            ])
            ->assertForbidden();
    }

    public function test_a_location_photo_is_re_encoded_rather_than_stored_as_uploaded(): void
    {
        Storage::fake('public');

        $owner = $this->owner();
        $location = Location::factory()->for($owner->places()->first())->create();

        $this->actingAs($owner)
            ->post("/owner/locations/{$location->id}/images", [
                'image' => UploadedFile::fake()->image('room.jpg', 2400, 1800),
            ])
            ->assertSessionHasNoErrors();

        $image = $location->images()->first();

        $this->assertNotNull($image);

        // Re-encoded on the way in: it strips EXIF, which on a phone photo
        // carries the photographer's own GPS, and caps a multi-megabyte
        // original that every visitor would otherwise download.
        $this->assertStringEndsWith('.webp', $image->path);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_a_photo_cannot_be_added_to_another_owners_location(): void
    {
        Storage::fake('public');

        $location = Location::factory()->for($this->owner()->places()->first())->create();

        $intruder = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($intruder)->create();

        $this->actingAs($intruder)
            ->post("/owner/locations/{$location->id}/images", [
                'image' => UploadedFile::fake()->image('room.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_the_public_event_page_carries_its_own_location(): void
    {
        $place = Place::factory()->create();
        $here = Location::factory()->for($place)->create([
            'name_en' => 'Rooftop',
            'latitude' => 32.7093878,
            'longitude' => 36.5687496,
            'landmark_en' => 'Above Cloud Cafe',
        ]);
        $event = Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'location_id' => $here->id,
        ]);

        $this->get("/{$place->slug}/{$event->slug}?lang=en")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.location.name', 'Rooftop')
                ->where('event.location.lat', 32.7093878)
                ->where('event.location.landmark_en', 'Above Cloud Cafe')
            );
    }

    public function test_an_event_without_one_falls_back_to_the_venue_default(): void
    {
        $place = Place::factory()->create();
        Location::factory()->for($place)->primary()->create(['name_en' => 'Main Hall']);
        $event = Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
            'location_id' => null,
        ]);

        $this->get("/{$place->slug}/{$event->slug}?lang=en")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('event.location.name', 'Main Hall'));
    }

    public function test_a_venue_with_no_locations_exposes_none(): void
    {
        $place = Place::factory()->create();
        $event = Event::factory()->for($place)->create(['status' => EventStatus::Published]);

        $this->get("/{$place->slug}/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('event.location', null));
    }
}
