<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $kind venue | organiser
 * @property bool $shares_locations
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $logo_path
 * @property string|null $whatsapp_number
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property string|null $representative_name
 * @property string|null $representative_role
 * @property string|null $representative_phone
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'slug', 'kind', 'name_ar', 'name_en', 'logo_path', 'whatsapp_number', 'is_active', 'shares_locations',
    'legal_name', 'registration_number', 'representative_name', 'representative_role', 'representative_phone',
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
            'shares_locations' => 'boolean',
        ];
    }

    public const KIND_VENUE = 'venue';

    public const KIND_ORGANISER = 'organiser';

    /**
     * An organiser runs events but owns no room: every event of theirs
     * happens at a venue that shares its locations.
     */
    public function isOrganiser(): bool
    {
        return $this->kind === self::KIND_ORGANISER;
    }

    /**
     * Locations this place may hold an event at: its own, plus every
     * location of an active venue that has opened its doors to others.
     *
     * @return Builder<Location>
     */
    public function usableLocations(): Builder
    {
        return Location::query()
            ->where(fn ($query) => $query
                ->where('place_id', $this->id)
                ->orWhereIn('place_id', self::query()
                    ->where('shares_locations', true)
                    ->where('is_active', true)
                    ->whereKeyNot($this->id)
                    ->select('id')))
            ->with('place:id,slug,name_ar,name_en')
            ->orderByRaw('place_id = ? desc', [$this->id])
            ->orderBy('sort');
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

    /** @return HasMany<AgreementAcceptance, $this> */
    public function acceptances(): HasMany
    {
        return $this->hasMany(AgreementAcceptance::class);
    }

    /**
     * Whether this venue has accepted a given version of the terms.
     */
    public function hasAccepted(AgreementVersion $version): bool
    {
        return $this->acceptances()->where('agreement_version_id', $version->id)->exists();
    }

    /** @return HasMany<CommercialOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(CommercialOffer::class);
    }

    /** @return HasMany<ServiceOrder, $this> */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class)->orderBy('sort');
    }

    /**
     * The location an event falls back to, and the one the venue shows.
     *
     * Falls back to the first location when no primary is flagged, so a place
     * whose primary was deleted still resolves to something.
     */
    public function primaryLocation(): ?Location
    {
        return $this->locations()->orderByDesc('is_primary')->orderBy('sort')->first();
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }
}
