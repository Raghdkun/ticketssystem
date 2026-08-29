<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place an event actually happens.
 *
 * @property int $id
 * @property int $place_id
 * @property string $name_ar
 * @property string $name_en
 * @property numeric-string|null $latitude
 * @property numeric-string|null $longitude
 * @property string|null $address_ar
 * @property string|null $address_en
 * @property string|null $landmark_ar
 * @property string|null $landmark_en
 * @property bool $is_primary
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'place_id', 'name_ar', 'name_en', 'latitude', 'longitude',
    'address_ar', 'address_en', 'landmark_ar', 'landmark_en', 'is_primary', 'sort',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return HasMany<LocationImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(LocationImage::class)->orderBy('sort');
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

    /** A location is only mappable once it has both halves of a coordinate. */
    public function hasPin(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * What the public needs to show this location.
     *
     * Returns null rather than a half-filled shape when there is no pin *and*
     * no address, so the caller decides once whether there is anything to show.
     *
     * @return array{name: string, lat: float|null, lng: float|null, address_ar: string|null, address_en: string|null, landmark_ar: string|null, landmark_en: string|null, images: list<string>}|null
     */
    public function forPublic(?string $locale = null): ?array
    {
        $hasAddress = filled($this->address_ar) || filled($this->address_en);

        if (! $this->hasPin() && ! $hasAddress) {
            return null;
        }

        return [
            'name' => $this->name($locale),
            'lat' => $this->latitude === null ? null : (float) $this->latitude,
            'lng' => $this->longitude === null ? null : (float) $this->longitude,
            'address_ar' => $this->address_ar,
            'address_en' => $this->address_en,
            'landmark_ar' => $this->landmark_ar,
            'landmark_en' => $this->landmark_en,
            'images' => array_values($this->images->map(
                fn (LocationImage $image): string => '/storage/'.$image->path
            )->all()),
        ];
    }
}
