<?php

namespace App\Models;

use Database\Factories\EventRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string $body_ar
 * @property string $body_en
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body_ar', 'body_en', 'sort'])]
class EventRule extends Model
{
    /** @use HasFactory<EventRuleFactory> */
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
