<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\ThemeMode;
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
 * @property string $slug
 * @property string $title_ar
 * @property string $title_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property string|null $cover_path
 * @property array<string, string>|null $cover_variants
 * @property string $price
 * @property string $currency
 * @property int $total_quantity
 * @property int $max_per_appointment
 * @property int $hold_hours
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable $appointments_close_at
 * @property EventStatus $status
 * @property ThemeMode $theme_mode
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property string|null $on_primary_color
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Place $place
 */
#[Fillable([
    'slug', 'title_ar', 'title_en', 'description_ar', 'description_en',
    'price', 'currency', 'total_quantity', 'max_per_appointment', 'hold_hours',
    'starts_at', 'ends_at', 'appointments_close_at', 'status',
    'theme_mode', 'primary_color', 'secondary_color',
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
            'theme_mode' => ThemeMode::class,
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
