<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $logo_path
 * @property string|null $whatsapp_number
 * @property bool $is_active
 * @property numeric-string|null $latitude
 * @property numeric-string|null $longitude
 * @property string|null $address_ar
 * @property string|null $address_en
 * @property string|null $landmark_ar
 * @property string|null $landmark_en
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'slug', 'name_ar', 'name_en', 'logo_path', 'whatsapp_number', 'is_active',
    'latitude', 'longitude', 'address_ar', 'address_en', 'landmark_ar', 'landmark_en',
])]
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * A venue is only mappable once it has both halves of a coordinate.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * What the public needs to show a venue on a map.
     *
     * Returns null rather than a half-filled shape when there is no pin, so
     * the caller decides once whether there is anything to render.
     *
     * @return array{lat: float, lng: float, address_ar: string|null, address_en: string|null, landmark_ar: string|null, landmark_en: string|null}|null
     */
    public function location(): ?array
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
            'address_ar' => $this->address_ar,
            'address_en' => $this->address_en,
            'landmark_ar' => $this->landmark_ar,
            'landmark_en' => $this->landmark_en,
        ];
    }
}
