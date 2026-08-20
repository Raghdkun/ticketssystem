<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPerk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPerk>
 */
class EventPerkFactory extends Factory
{
    protected $model = EventPerk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'body_ar' => 'مشروب مجاني',
            'body_en' => 'One free drink',
            'sort' => 0,
        ];
    }
}
