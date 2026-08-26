<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlaceLocationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'owner']);
        Place::factory()->for($owner)->create();

        return $owner;
    }

    public function test_an_owner_can_pin_their_venue(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch('/owner/place', [
                'name_ar' => 'قاعة السويداء',
                'name_en' => 'Suwayda Hall',
                'latitude' => 32.7093878,
                'longitude' => 36.5687496,
                'address_ar' => 'شارع القوتلي',
                'address_en' => 'Al-Qwatli Street',
                'landmark_ar' => 'بجانب الجامع القديم',
                'landmark_en' => 'Beside the old mosque',
            ])
            ->assertSessionHasNoErrors();

        $place = $owner->places()->first();

        $this->assertNotNull($place->location());
        $this->assertSame(32.7093878, $place->location()['lat']);
        $this->assertSame('Beside the old mosque', $place->landmark_en);
    }

    public function test_half_a_coordinate_is_rejected(): void
    {
        $owner = $this->owner();

        // Longitude without latitude silently becomes (0, lng) -- a point in
        // the Gulf of Guinea -- if the pair is not validated together.
        $this->actingAs($owner)
            ->patch('/owner/place', [
                'name_ar' => 'قاعة',
                'name_en' => 'Hall',
                'longitude' => 36.5687496,
            ])
            ->assertSessionHasErrors('latitude');

        $this->assertNull($owner->places()->first()->location());
    }

    public function test_an_owner_cannot_reach_another_owners_venue(): void
    {
        $this->owner();
        $intruder = User::factory()->create(['role' => 'owner']);

        // The intruder has no venue of their own, so there is nothing to edit.
        $this->actingAs($intruder)
            ->patch('/owner/place', [
                'name_ar' => 'مختطفة',
                'name_en' => 'Hijacked',
            ])
            ->assertNotFound();
    }

    public function test_the_pin_can_be_cleared(): void
    {
        $owner = $this->owner();
        $owner->places()->first()->update([
            'latitude' => 32.7, 'longitude' => 36.5,
        ]);

        $this->actingAs($owner)
            ->patch('/owner/place', [
                'name_ar' => 'قاعة',
                'name_en' => 'Hall',
                'latitude' => '',
                'longitude' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($owner->places()->first()->fresh()->location());
    }

    public function test_the_public_event_page_carries_the_venue_location(): void
    {
        $owner = $this->owner();
        $place = $owner->places()->first();
        $place->update([
            'latitude' => 32.7093878,
            'longitude' => 36.5687496,
            'landmark_en' => 'Beside the old mosque',
        ]);

        $event = Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
        ]);

        $this->get("/{$place->slug}/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.location.lat', 32.7093878)
                ->where('place.location.landmark_en', 'Beside the old mosque')
            );
    }

    public function test_a_venue_without_a_pin_exposes_no_location(): void
    {
        $place = $this->owner()->places()->first();
        $event = Event::factory()->for($place)->create([
            'status' => EventStatus::Published,
        ]);

        $this->get("/{$place->slug}/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('place.location', null));
    }
}
