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
            ['owner' => 'Rawan Owner', 'email' => 'owner@example.com', 'place' => 'Swaida Grand Hall'],
            ['owner' => 'Samer Owner', 'email' => 'owner2@example.com', 'place' => 'Cloud Nine Rooftop'],
        ])->each(function (array $data) use ($rules) {
            $user = User::factory()->create([
                'name' => $data['owner'],
                'email' => $data['email'],
            ]);

            $place = Place::factory()->for($user)->create([
                'name_en' => $data['place'],
                'name_ar' => 'قاعة '.$data['place'],
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
