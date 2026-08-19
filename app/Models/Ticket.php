<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $event_id
 * @property string $public_token
 * @property string $full_name
 * @property string $phone
 * @property int $quantity
 * @property TicketStatus $status
 * @property CarbonImmutable|null $hold_expires_at
 * @property CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $accepted_rules_at
 * @property array<int, int> $accepted_rule_ids
 * @property string $locale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Event $event
 */
#[Fillable(['full_name', 'phone', 'quantity'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Tickets are always addressed by their unguessable token, never by id.
     */
    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'accepted_rule_ids' => 'array',
            'hold_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'accepted_rules_at' => 'datetime',
        ];
    }

    public static function generateToken(): string
    {
        return Str::lower(Str::random(32));
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<TicketStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class)->orderBy('created_at');
    }

    /**
     * Tickets occupying inventory: paid outright, or pending with a live hold.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeHoldingSeats(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->where('status', TicketStatus::Paid)
                ->orWhere(function (Builder $query) {
                    $query->where('status', TicketStatus::Pending)
                        ->where('hold_expires_at', '>', now());
                });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeLapsed(Builder $query): void
    {
        $query->where('status', TicketStatus::Pending)
            ->where('hold_expires_at', '<=', now());
    }

    public function isPending(): bool
    {
        return $this->status === TicketStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === TicketStatus::Paid;
    }
}
