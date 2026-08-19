<?php

namespace App\Models;

use App\Enums\MediaType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property MediaType $type
 * @property string $path
 * @property string|null $poster_path
 * @property string $mime
 * @property int $size_bytes
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['type', 'path', 'poster_path', 'mime', 'size_bytes', 'sort'])]
class EventMedia extends Model
{
    protected $table = 'event_media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => MediaType::class];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::Video;
    }
}
