<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventWatcherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody waiting for a seat on a sold-out event.
 *
 * `notified_at` is set once a seat has been offered, so a queue is worked
 * through rather than shouted at repeatedly. It is deliberately not cleared
 * when the event fills up again: being told twice about the same event is
 * worse than being told once and missing out.
 *
 * @property int $id
 * @property int $event_id
 * @property string $full_name
 * @property string $phone
 * @property string $locale
 * @property string|null $fcm_token
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $created_at
 * @property-read Event $event
 */
#[Fillable(['full_name', 'phone', 'locale', 'fcm_token'])]
class EventWatcher extends Model
{
    /** @use HasFactory<EventWatcherFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Still waiting to hear that a seat came back.
     *
     * @param  Builder<EventWatcher>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query->whereNull('notified_at');
    }
}
