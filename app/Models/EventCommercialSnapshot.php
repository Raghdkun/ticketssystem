<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The commercial terms an event was published under.
 *
 * Copied from the venue's accepted offer at the moment the owner publishes
 * and never touched again: the venue may accept a different offer next
 * month, and the terms this event was sold under must not move with it.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $commercial_offer_id
 * @property string $fee_type
 * @property string|null $fee_value
 * @property string $fee_payer
 * @property int|null $settlement_days
 * @property string|null $currency
 * @property int|null $accepted_by
 * @property CarbonImmutable $accepted_at
 * @property string|null $ip
 * @property-read Event $event
 * @property-read CommercialOffer|null $offer
 */
#[Fillable([
    'event_id', 'commercial_offer_id', 'fee_type', 'fee_value', 'fee_payer', 'settlement_days',
    'currency', 'accepted_by', 'accepted_at', 'ip',
])]
class EventCommercialSnapshot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_value' => 'decimal:2',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('An event commercial snapshot is written once and never updated.');
        });
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<CommercialOffer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class, 'commercial_offer_id');
    }
}
