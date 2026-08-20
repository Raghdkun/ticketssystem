<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EventPerkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something included with the ticket: a free drink, a reserved seat. Kept
 * separate from rules, which are obligations the attendee accepts.
 *
 * @property int $id
 * @property int $event_id
 * @property string $body_ar
 * @property string $body_en
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['body_ar', 'body_en', 'sort'])]
class EventPerk extends Model
{
    /** @use HasFactory<EventPerkFactory> */
    use HasFactory;

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function body(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->body_ar : $this->body_en;
    }
}
