<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token_hash
 * @property string $email
 * @property int|null $invited_by
 * @property int|null $place_id
 * @property string $role
 * @property bool $requires_approval
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property int|null $accepted_user_id
 */
#[Fillable(['token_hash', 'email', 'invited_by', 'requires_approval', 'expires_at', 'place_id', 'role'])]
class OwnerInvitation extends Model
{
    /** Long enough that guessing is not a strategy. */
    private const TOKEN_BYTES = 32;

    public const ROLE_OWNER = 'owner';

    /** Works one venue's door and nothing else. */
    public const ROLE_STAFF = 'door_staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Mints an invitation and hands back the one copy of its raw token.
     *
     * The raw token is never stored and never recoverable: an administrator
     * who loses the link issues a new invitation rather than looking the old
     * one up.
     *
     * @return array{invitation: self, token: string}
     */
    public static function mint(
        string $email,
        User $by,
        bool $requiresApproval,
        int $days = 7,
        string $role = self::ROLE_OWNER,
        ?int $placeId = null,
    ): array {
        $token = Str::random(self::TOKEN_BYTES * 2);

        $invitation = self::create([
            'token_hash' => self::hash($token),
            'email' => $email,
            'invited_by' => $by->id,
            'requires_approval' => $requiresApproval,
            'expires_at' => now()->addDays($days),
            'role' => $role,
            // Door staff belong to one venue; an owner brings their own.
            'place_id' => $role === self::ROLE_STAFF ? $placeId : null,
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isForStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Place, $this> */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
