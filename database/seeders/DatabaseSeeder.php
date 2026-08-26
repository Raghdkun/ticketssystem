<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRule;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $rules = [
            ['body_ar' => 'للأعمار فوق ١٨ سنة فقط', 'body_en' => '+18 only'],
            ['body_ar' => 'للأزواج فقط', 'body_en' => 'Couples only'],
            ['body_ar' => 'يرجى الالتزام بالزي الرسمي', 'body_en' => 'Formal dress code'],
        ];

        collect([
            [
                'owner' => 'Rawan Owner', 'email' => 'owner@example.com', 'place' => 'Swaida Grand Hall',
                // Real As-Suwayda coordinates, so the seeded map is not a
                // plausible-looking pin in the wrong country.
                'lat' => 32.7093878, 'lng' => 36.5687496,
                'address_ar' => 'شارع القوتلي، السويداء', 'address_en' => 'Al-Qwatli Street, As-Suwayda',
                'landmark_ar' => 'مقابل السرايا القديمة', 'landmark_en' => 'Opposite the old Saraya',
            ],
            [
                'owner' => 'Samer Owner', 'email' => 'owner2@example.com', 'place' => 'Cloud Nine Rooftop',
                'lat' => 32.7051, 'lng' => 36.5641,
                'address_ar' => 'حي المزرعة، السويداء', 'address_en' => 'Al-Mazraa, As-Suwayda',
                'landmark_ar' => 'فوق مقهى الغيمة', 'landmark_en' => 'Above Cloud Cafe',
            ],
        ])->each(function (array $data) use ($rules) {
            $user = User::factory()->create([
                'name' => $data['owner'],
                'email' => $data['email'],
            ]);

            $place = Place::factory()->for($user)->create([
                'name_en' => $data['place'],
                'name_ar' => 'قاعة '.$data['place'],
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'address_ar' => $data['address_ar'],
                'address_en' => $data['address_en'],
                'landmark_ar' => $data['landmark_ar'],
                'landmark_en' => $data['landmark_en'],
            ]);

            Event::factory()->count(3)->for($place)->create()->each(
                function (Event $event) use ($rules) {
                    foreach ($rules as $i => $rule) {
                        EventRule::factory()->for($event)->create([...$rule, 'sort' => $i]);
                    }

                    Ticket::factory()->count(4)->for($event)->create();
                    Ticket::factory()->count(2)->paid()->for($event)->create();
                }
            );
        });
    }
}
