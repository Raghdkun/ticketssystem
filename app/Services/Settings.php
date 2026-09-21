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
        'app_name_en' => 'Nas',
        'app_name_ar' => 'ناس',
        'tagline_en' => 'Reserve your seat, pay at the venue.',
        'tagline_ar' => 'احجز مقعدك، وادفع في المكان.',
        'logo_path' => null,
        'icon_path' => null,
        'support_whatsapp' => null,
        // The pitch on /for-venues. A few lines an administrator rewrites
        // from the dashboard; the shipped text is a starting point.
        'venues_pitch_ar' => "الناس يحجزون مقاعدهم من هواتفهم، ويدفعون عند بابك.\nأنت تتحقق من التذكرة بمسح رمز، ولا تحتاج إلى بوابة دفع.\nلا اقتطاع من سعر التذكرة، ولا أجهزة، ولا تدريب.\nتحدّث معنا وسنجهّز لك حسابًا.",
        'venues_pitch_en' => "People reserve their seats from their phones and pay at your door.\nYou verify a ticket with one scan. No payment gateway needed.\nNothing is taken from the ticket price. No hardware, no training.\nTalk to us and we will set up your account.",
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
