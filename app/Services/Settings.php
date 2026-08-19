<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide settings a super admin can change without a deploy.
 *
 * Read on nearly every request, so the whole set is cached as one entry and
 * flushed on write rather than queried per key.
 */
final class Settings
{
    private const CACHE_KEY = 'platform.settings';

    /** Defaults are the shipped brand; the database only holds overrides. */
    public const DEFAULTS = [
        'app_name_en' => 'Swaida Tickets Hub',
        'app_name_ar' => 'مركز تذاكر السويداء',
        'tagline_en' => 'Reserve your seat, pay at the venue.',
        'tagline_ar' => 'احجز مقعدك، وادفع في المكان.',
        'logo_path' => null,
        'icon_path' => null,
        'support_whatsapp' => null,
    ];

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        /** @var array<string, string|null> $stored */
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => PlatformSetting::query()->pluck('value', 'key')->all()
        );

        return [...self::DEFAULTS, ...array_filter($stored, fn ($v) => $v !== null && $v !== '')];
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function put(array $values, ?int $actorId = null): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $actorId],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function appName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->get($locale === 'ar' ? 'app_name_ar' : 'app_name_en')
            ?? self::DEFAULTS['app_name_en'];
    }
}
