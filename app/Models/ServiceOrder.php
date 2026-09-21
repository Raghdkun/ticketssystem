<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One paid service on top of a venue's commercial arrangement.
 *
 * Door staff for a night, a photographer, a promotion run. Written by an
 * administrator, sent, accepted by the venue, then worked through
 * in_progress, completed or cancelled. The terms freeze when it is sent;
 * only the status moves after that.
 *
 * @property int $id
 * @property int $place_id
 * @property int|null $event_id
 * @property string $status
 * @property string $service_type
 * @property string $title_ar
 * @property string $title_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property int $quantity
 * @property string|null $unit_price
 * @property string|null $currency
 * @property string|null $terms_ar
 * @property string|null $terms_en
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
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property-read Place $place
 * @property-read Event|null $event
 */
#[Fillable([
    'place_id', 'event_id', 'service_type', 'title_ar', 'title_en', 'description_ar', 'description_en',
    'quantity', 'unit_price', 'currency', 'terms_ar', 'terms_en',
])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'sent', 'accepted', 'in_progress', 'completed', 'cancelled'];

    public const TYPES = [
        'door_staff', 'registration_staff', 'event_management', 'promotion',
        'photography', 'video', 'design', 'equipment', 'logistics', 'other',
    ];

    /**
     * Frozen from the moment the order is sent.
     *
     * @var list<string>
     */
    public const TERMS = [
        'place_id', 'event_id', 'service_type', 'title_ar', 'title_en', 'description_ar', 'description_en',
        'quantity', 'unit_price', 'currency', 'terms_ar', 'terms_en', 'content_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ServiceOrder $order) {
            if ($order->getRawOriginal('status') === 'draft') {
                return;
            }

            $touched = array_intersect(array_keys($order->getDirty()), self::TERMS);

            if ($touched !== []) {
                throw new LogicException(
                    'The terms of a sent order are immutable. Attempted to change: '.implode(', ', $touched)
                );
            }
        });

        static::deleting(function (ServiceOrder $order) {
            if ($order->status !== 'draft') {
                throw new LogicException('Only a draft order may be deleted.');
            }
        });
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
        return in_array($this->status, ['accepted', 'in_progress', 'completed'], true);
    }

    public function total(): ?float
    {
        return $this->unit_price === null ? null : (float) $this->unit_price * $this->quantity;
    }

    public function hashContent(): string
    {
        return hash('sha256', implode("\n---\n", [
            $this->place_id, (string) $this->event_id, $this->service_type,
            $this->title_ar, $this->title_en, (string) $this->description_ar, (string) $this->description_en,
            $this->quantity, (string) $this->unit_price, (string) $this->currency,
            (string) $this->terms_ar, (string) $this->terms_en,
        ]));
    }
}
