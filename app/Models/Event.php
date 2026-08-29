<?php

namespace App\Models;

use App\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $place_id
 * @property int|null $location_id
 * @property string $slug
 * @property string $title_ar
 * @property string $title_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property string|null $cover_path
 * @property array<string, string>|null $cover_variants
 * @property int|null $promo_video_id
 * @property string $price
 * @property string $currency
 * @property int $total_quantity
 * @property int $max_per_appointment
 * @property int $hold_hours
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable $appointments_close_at
 * @property EventStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Place $place
 */
#[Fillable([
    'slug', 'location_id', 'title_ar', 'title_en', 'description_ar', 'description_en',
    'price', 'currency', 'total_quantity', 'max_per_appointment', 'hold_hours',
    'starts_at', 'ends_at', 'appointments_close_at', 'status',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cover_variants' => 'array',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'appointments_close_at' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return HasMany<EventRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(EventRule::class)->orderBy('sort');
    }

    /** @return HasMany<EventMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class)->orderBy('sort');
    }

    /** @return HasMany<EventPerk, $this> */
    public function perks(): HasMany
    {
        return $this->hasMany(EventPerk::class)->orderBy('sort');
    }

    /** @return BelongsTo<EventMedia, $this> */
    public function promoVideo(): BelongsTo
    {
        return $this->belongsTo(EventMedia::class, 'promo_video_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Where this event actually happens.
     *
     * An event without its own location falls back to the venue's primary one,
     * so an event drafted before locations existed keeps showing an address.
     */
    public function resolvedLocation(): ?Location
    {
        return $this->location ?? $this->place->primaryLocation();
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->title_ar : $this->title_en;
    }

    /**
     * Seats consumed by tickets that still hold inventory. A pending ticket
     * whose hold has lapsed is not counted, so seats free up correctly even if
     * the expiry command has not run yet.
     */
    public function description(?string $locale = null): ?string
    {
        return ($locale ?? app()->getLocale()) === 'ar'
            ? $this->description_ar
            : $this->description_en;
    }

    public function seatsTaken(): int
    {
        return (int) $this->tickets()->holdingSeats()->sum('quantity');
    }

    public function seatsRemaining(): int
    {
        return max(0, $this->total_quantity - $this->seatsTaken());
    }

    /**
     * Whether the event can currently accept new appointments, ignoring seat
     * availability (which must be checked under a row lock).
     */
    public function isOpenForAppointments(): bool
    {
        return $this->status === EventStatus::Published
            && $this->appointments_close_at->isFuture();
    }
}
