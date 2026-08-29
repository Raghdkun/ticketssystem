<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_super_admin
 * @property bool $requires_approval
 * @property int|null $door_staff_for
 * @property CarbonImmutable|null $banned_at
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'requires_approval' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    /** @return HasMany<Place, $this> */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /** @return BelongsTo<Place, $this> */
    public function doorStaffVenue(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'door_staff_for');
    }

    /**
     * Somebody who works a venue's door and nothing else.
     *
     * They can check people in and look up a ticket. They cannot create
     * events, see what the venue took, or invite anybody.
     */
    public function isDoorStaff(): bool
    {
        return $this->door_staff_for !== null;
    }

    /**
     * The venue this account works at, however it came by it.
     *
     * Owners own theirs; door staff are assigned one. Everything at the door
     * -- scanning, verifying, searching -- resolves the place through here so
     * it works the same for both.
     */
    public function workingPlace(): ?Place
    {
        return $this->places()->first() ?? $this->doorStaffVenue;
    }

    /**
     * Whether this account may change what a venue offers.
     *
     * The dividing line for the whole owner area: events, locations, the
     * venue itself, reports and invitations sit behind it; the door does not.
     */
    public function managesVenue(): bool
    {
        return ! $this->isDoorStaff();
    }

    /**
     * Whether this account manages a venue.
     *
     * Ownership is not a role any more, it is simply whether they own
     * anything -- which means an administrator can run a hall too.
     */
    public function isOwner(): bool
    {
        return $this->places()->exists();
    }

    /**
     * Whether publishing an event needs a super admin's sign-off.
     *
     * The gate is on publishing only: drafting and editing stay free, so an
     * owner is never stuck waiting to do their own preparation.
     */
    public function needsPublishApproval(): bool
    {
        return $this->requires_approval && ! $this->isSuperAdmin();
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }
}
