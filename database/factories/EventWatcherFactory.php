<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventWatcher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventWatcher>
 */
class EventWatcherFactory extends Factory
{
    protected $model = EventWatcher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'full_name' => $this->faker->name(),
            'phone' => '+9639'.$this->faker->numerify('########'),
            'locale' => 'ar',
            'fcm_token' => null,
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state(fn () => ['notified_at' => now()]);
    }
}
