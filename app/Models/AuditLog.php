<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $subject_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property string|null $ip
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['actor_id', 'subject_id', 'action', 'changes', 'ip'])]
class AuditLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /**
     * Records an act against a person.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function record(string $action, ?User $subject, array $changes = []): self
    {
        return self::create([
            'actor_id' => auth()->id(),
            'subject_id' => $subject?->id,
            'action' => $action,
            'changes' => $changes ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
