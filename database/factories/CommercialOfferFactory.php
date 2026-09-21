<?php

namespace Database\Factories;

use App\Models\CommercialOffer;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercialOffer>
 */
class CommercialOfferFactory extends Factory
{
    protected $model = CommercialOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => Place::factory(),
            'title_ar' => 'العرض التجاري',
            'title_en' => 'Commercial offer',
            'fee_type' => 'percentage',
            'fee_value' => 5,
            'fee_payer' => 'customer',
            'settlement_days' => 3,
            'currency' => 'SYP',
            'included_services_ar' => 'إدارة التذاكر والتحقق عند الباب',
            'included_services_en' => 'Ticketing and door verification',
            'status' => 'draft',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent', 'sent_at' => now()])
            ->afterCreating(fn (CommercialOffer $offer) => $offer->forceFill(['content_hash' => $offer->hashContent()])->saveQuietly());
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
            'representative_name' => 'Samer Haddad',
            'representative_role' => 'manager',
            'representative_phone' => '+963991234567',
            'acceptance_method' => 'clickwrap_verified_account',
        ])->afterCreating(fn (CommercialOffer $offer) => $offer->forceFill(['content_hash' => $offer->hashContent()])->saveQuietly());
    }
}
