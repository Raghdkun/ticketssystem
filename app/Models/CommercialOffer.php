<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CommercialOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One venue's commercial arrangement with the platform.
 *
 * Written by an administrator, sent, and accepted or declined by the venue.
 * The terms freeze the moment it is sent: what the venue reads is what it
 * signs. A newer offer, once accepted, supersedes the one before it.
 *
 * @property int $id
 * @property int $place_id
 * @property string $status
 * @property string $title_ar
 * @property string $title_en
 * @property string $fee_type
 * @property string|null $fee_value
 * @property string $fee_payer
 * @property int|null $settlement_days
 * @property string|null $subscription_amount
 * @property string|null $currency
 * @property string|null $included_services_ar
 * @property string|null $included_services_en
 * @property string|null $additional_terms_ar
 * @property string|null $additional_terms_en
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property string|null $content_hash
 * @property int|null $created_by
 * @property CarbonImmutable|null $sent_at
 * @property int|null $accepted_by
 * @property CarbonImmutable|null $accepted_at
 * @property string|null $representative_name
 * @property string|null $representative_role
 * @property string|null $representative_phone
 * @property string|null $acceptance_method
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $superseded_at
 * @property int|null $superseded_by_id
 * @property-read Place $place
 * @property-read User|null $acceptor
 */
#[Fillable([
    'place_id', 'title_ar', 'title_en', 'fee_type', 'fee_value', 'fee_payer', 'settlement_days',
    'subscription_amount', 'currency', 'included_services_ar', 'included_services_en',
    'additional_terms_ar', 'additional_terms_en', 'valid_from', 'valid_until',
])]
class CommercialOffer extends Model
{
    /** @use HasFactory<CommercialOfferFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'superseded'];

    public const FEE_TYPES = ['percentage', 'fixed', 'subscription', 'custom'];

    public const FEE_PAYERS = ['customer', 'organizer', 'split', 'custom'];

    /**
     * What the venue agrees to. Frozen from the moment the offer is sent.
     *
     * @var list<string>
     */
    public const TERMS = [
        'place_id', 'title_ar', 'title_en', 'fee_type', 'fee_value', 'fee_payer', 'settlement_days',
        'subscription_amount', 'currency', 'included_services_ar', 'included_services_en',
        'additional_terms_ar', 'additional_terms_en', 'valid_from', 'valid_until', 'content_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_value' => 'decimal:2',
            'subscription_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CommercialOffer $offer) {
            if ($offer->getRawOriginal('status') === 'draft') {
                return;
            }

            $touched = array_intersect(array_keys($offer->getDirty()), self::TERMS);

            if ($touched !== []) {
                throw new LogicException(
                    'The terms of a sent offer are immutable; write a new offer instead. Attempted to change: '.implode(', ', $touched)
                );
            }
        });

        static::deleting(function (CommercialOffer $offer) {
            if ($offer->status !== 'draft') {
                throw new LogicException('Only a draft offer may be deleted.');
            }
        });
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @param  Builder<CommercialOffer>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('status', 'accepted');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * A sent offer past its validity date can no longer be accepted.
     * Computed rather than stored: nothing has to run for it to be true.
     */
    public function isExpired(): bool
    {
        return $this->isSent()
            && $this->valid_until !== null
            && $this->valid_until->endOfDay()->isPast();
    }

    public function hashContent(): string
    {
        return hash('sha256', implode("\n---\n", [
            $this->place_id, $this->title_ar, $this->title_en,
            $this->fee_type, (string) $this->fee_value, $this->fee_payer, (string) $this->settlement_days,
            (string) $this->subscription_amount, (string) $this->currency,
            (string) $this->included_services_ar, (string) $this->included_services_en,
            (string) $this->additional_terms_ar, (string) $this->additional_terms_en,
            (string) $this->valid_from?->toDateString(), (string) $this->valid_until?->toDateString(),
        ]));
    }

    /**
     * The figures an event snapshot copies.
     *
     * @return array{fee_type: string, fee_value: string|null, fee_payer: string, settlement_days: int|null, currency: string|null}
     */
    public function figures(): array
    {
        return [
            'fee_type' => $this->fee_type,
            'fee_value' => $this->fee_value,
            'fee_payer' => $this->fee_payer,
            'settlement_days' => $this->settlement_days,
            'currency' => $this->currency,
        ];
    }
}
