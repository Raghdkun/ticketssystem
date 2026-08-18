<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRule>
 */
class EventRuleFactory extends Factory
{
    protected $model = EventRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'body_ar' => 'للأعمار فوق ١٨ سنة فقط',
            'body_en' => '+18 only',
            'sort' => 0,
        ];
    }
}
