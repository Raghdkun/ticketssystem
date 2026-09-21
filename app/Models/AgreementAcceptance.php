<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One venue's acceptance of one version of the terms.
 *
 * Written once and never updated: the row is the evidence. Everything that
 * identifies the signer is copied in, not referenced, so the venue can
 * change its name or its representative later without the record moving.
 *
 * @property int $id
 * @property int $agreement_version_id
 * @property int $place_id
 * @property int|null $user_id
 * @property string $legal_name
 * @property string|null $registration_number
 * @property string $representative_name
 * @property string $representative_role
 * @property string $representative_phone
 * @property string|null $email_snapshot
 * @property bool $authority_claimed
 * @property string $acceptance_method
 * @property string $content_hash
 * @property string $locale
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $otp_channel
 * @property CarbonImmutable|null $otp_verified_at
 * @property CarbonImmutable $accepted_at
 * @property-read AgreementVersion $version
 * @property-read Place $place
 * @property-read User|null $user
 */
#[Fillable([
    'agreement_version_id', 'place_id', 'user_id',
    'legal_name', 'registration_number', 'representative_name', 'representative_role', 'representative_phone',
    'email_snapshot', 'authority_claimed', 'acceptance_method', 'content_hash', 'locale', 'ip', 'user_agent', 'otp_channel', 'otp_verified_at', 'accepted_at',
])]
class AgreementAcceptance extends Model
{
    /** The roles a representative may sign in. */
    public const ROLES = ['owner', 'manager', 'organiser', 'authorised', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authority_claimed' => 'boolean',
            'otp_verified_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AgreementVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(AgreementVersion::class, 'agreement_version_id');
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
