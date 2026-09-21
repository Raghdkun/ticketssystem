<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AgreementVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One version of the Partner Terms.
 *
 * @property int $id
 * @property string $kind
 * @property string $version
 * @property string $title_ar
 * @property string $title_en
 * @property string $body_ar
 * @property string $body_en
 * @property string|null $change_note_ar
 * @property string|null $change_note_en
 * @property AgreementStatus $status
 * @property bool $requires_reacceptance
 * @property string|null $content_hash
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 * @property CarbonImmutable|null $retired_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'kind', 'version', 'title_ar', 'title_en', 'body_ar', 'body_en',
    'change_note_ar', 'change_note_en', 'requires_reacceptance',
])]
class AgreementVersion extends Model
{
    /** @use HasFactory<AgreementVersionFactory> */
    use HasFactory;

    public const KIND_PARTNER_TERMS = 'partner_terms';

    /**
     * The columns that define what was agreed to. Once a version has been
     * published they cannot change, because acceptances point at them.
     *
     * @var list<string>
     */
    public const IMMUTABLE = [
        'kind', 'version', 'title_ar', 'title_en', 'body_ar', 'body_en',
        'change_note_ar', 'change_note_en', 'requires_reacceptance', 'content_hash', 'published_at', 'published_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgreementStatus::class,
            'requires_reacceptance' => 'boolean',
            'published_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The guard, in the model rather than the controller: nothing --
        // not a tinker session, not a future admin screen -- may rewrite
        // text that somebody has signed.
        static::updating(function (AgreementVersion $version) {
            if ($version->getRawOriginal('status') === AgreementStatus::Draft->value) {
                return;
            }

            $touched = array_intersect(array_keys($version->getDirty()), self::IMMUTABLE);

            if ($touched !== []) {
                throw new LogicException(
                    'A published agreement version is immutable; publish a new version instead. Attempted to change: '.implode(', ', $touched)
                );
            }
        });

        static::deleting(function (AgreementVersion $version) {
            if ($version->status !== AgreementStatus::Draft) {
                throw new LogicException('Only a draft agreement version may be deleted.');
            }
        });
    }

    /** @return HasMany<AgreementAcceptance, $this> */
    public function acceptances(): HasMany
    {
        return $this->hasMany(AgreementAcceptance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @param  Builder<AgreementVersion>  $query
     */
    public function scopeOfKind(Builder $query, string $kind = self::KIND_PARTNER_TERMS): void
    {
        $query->where('kind', $kind);
    }

    /**
     * The version in force, if any.
     */
    public static function current(string $kind = self::KIND_PARTNER_TERMS): ?self
    {
        return self::query()
            ->ofKind($kind)
            ->where('status', AgreementStatus::Published)
            ->latest('published_at')
            ->first();
    }

    public function isDraft(): bool
    {
        return $this->status === AgreementStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === AgreementStatus::Published;
    }

    /**
     * The fingerprint of the text, in both languages. An acceptance stores
     * the same value, so the record shows what was on the screen even if
     * somebody later found a way past the immutability guard.
     */
    public function hashContent(): string
    {
        return hash('sha256', implode("\n---\n", [
            $this->kind, $this->version,
            $this->title_ar, $this->title_en,
            $this->body_ar, $this->body_en,
        ]));
    }

    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->title_ar : $this->title_en;
    }

    public function body(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->body_ar : $this->body_en;
    }

    public function changeNote(?string $locale = null): ?string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->change_note_ar : $this->change_note_en;
    }
}
