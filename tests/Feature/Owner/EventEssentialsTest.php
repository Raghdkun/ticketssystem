<?php

namespace Tests\Feature\Owner;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEssentialsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        Place::factory()->for($owner)->create();

        return $owner;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title_ar' => 'حفل',
            'title_en' => 'Concert',
            'description_ar' => 'وصف',
            'description_en' => 'Description',
            'price' => 25000,
            'currency' => 'SYP',
            'total_quantity' => 100,
            'max_per_appointment' => 4,
            'hold_hours' => 24,
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'appointments_close_at' => now()->addWeeks(3)->toDateTimeString(),
            'status' => EventStatus::Draft->value,
            ...$overrides,
        ];
    }

    public function test_an_event_can_be_created_without_a_cover(): void
    {
        $owner = $this->owner();

        // An owner should be able to get an event dated and drafted before
        // they have artwork for it.
        $this->actingAs($owner)
            ->post('/owner/events', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Event::count());
        $this->assertNull(Event::first()->cover_variants);
    }

    public function test_the_event_qr_downloads_as_a_png(): void
    {
        $owner = $this->owner();
        $event = Event::factory()->for($owner->places()->first())->create();

        $response = $this->actingAs($owner)
            ->get("/owner/events/{$event->id}/qr.png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $body = $response->getContent();

        $this->assertStringStartsWith("\x89PNG", (string) $body);
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_another_owner_cannot_download_the_qr(): void
    {
        $event = Event::factory()->for($this->owner()->places()->first())->create();

        $intruder = User::factory()->create();
        Place::factory()->for($intruder)->create();

        $this->actingAs($intruder)
            ->get("/owner/events/{$event->id}/qr.png")
            ->assertForbidden();
    }
}
