<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'name_ar' => 'قاعة '.$name,
            'name_en' => $name,
            'logo_path' => null,
            'whatsapp_number' => '+963991234567',
            'is_active' => true,
        ];
    }
}
