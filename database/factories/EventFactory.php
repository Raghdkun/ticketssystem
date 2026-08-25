<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(3), '.');
        $startsAt = now()->addDays($this->faker->numberBetween(3, 30));

        return [
            'place_id' => Place::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title_ar' => 'حفل '.$title,
            'title_en' => $title,
            'description_ar' => 'وصف الفعالية باللغة العربية.',
            'description_en' => $this->faker->paragraph(),
            'price' => $this->faker->randomElement([0, 25000, 50000]),
            'currency' => 'SYP',
            'total_quantity' => $this->faker->numberBetween(50, 300),
            'max_per_appointment' => 10,
            'hold_hours' => 24,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(4),
            'appointments_close_at' => (clone $startsAt)->subDay(),
            'status' => EventStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['appointments_close_at' => now()->subDay()]);
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['total_quantity' => 1]);
    }
}
