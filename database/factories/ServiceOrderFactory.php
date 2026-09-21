<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    protected $model = ServiceOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => Place::factory(),
            'service_type' => 'door_staff',
            'title_ar' => 'خدمة تنظيم الدخول',
            'title_en' => 'Door staff',
            'description_ar' => 'أربعة أفراد لخمس ساعات، المواصلات مشمولة.',
            'description_en' => 'Four staff for five hours, transport included.',
            'quantity' => 4,
            'unit_price' => 50000,
            'currency' => 'SYP',
            'status' => 'draft',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent', 'sent_at' => now()])
            ->afterCreating(fn (ServiceOrder $order) => $order->forceFill(['content_hash' => $order->hashContent()])->saveQuietly());
    }
}
