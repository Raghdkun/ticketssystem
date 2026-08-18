<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'public_token' => Ticket::generateToken(),
            'full_name' => $this->faker->name(),
            'phone' => '+9639'.$this->faker->numerify('########'),
            'quantity' => $this->faker->numberBetween(1, 4),
            'status' => TicketStatus::Pending,
            'hold_expires_at' => now()->addDay(),
            'accepted_rules_at' => now(),
            'accepted_rule_ids' => [],
            'locale' => 'ar',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Paid,
            'verified_at' => now(),
            'hold_expires_at' => null,
        ]);
    }

    public function lapsed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Pending,
            'hold_expires_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
