<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->streetName();

        return [
            'place_id' => Place::factory(),
            'name_ar' => 'قاعة '.$name,
            'name_en' => $name,
            // Inside As-Suwayda, so a seeded map is not a plausible-looking
            // pin in the wrong country.
            'latitude' => fake()->randomFloat(7, 32.68, 32.73),
            'longitude' => fake()->randomFloat(7, 36.54, 36.60),
            'address_ar' => 'شارع '.$name,
            'address_en' => $name.' Street',
            'is_primary' => false,
            'sort' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function withoutPin(): static
    {
        return $this->state(fn () => ['latitude' => null, 'longitude' => null]);
    }
}
