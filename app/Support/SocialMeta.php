<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Place;
use Illuminate\Support\Str;

/**
 * Link-preview metadata, shared as an Inertia prop and rendered server-side.
 *
 * It has to be server-rendered: WhatsApp, Facebook and Telegram fetch the page
 * with a plain HTTP client and never execute JavaScript, so anything Inertia
 * writes into the head from React is invisible to them.
 */
final class SocialMeta
{
    /**
     * @return array{title: string, description: string|null, image: string|null, width: int|null, height: int|null, type: string}
     */
    public static function forEvent(Event $event, Place $place, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $cover = self::cover($event);

        return [
            'title' => $event->title($locale).' — '.$place->name($locale),
            'description' => self::trim($event->description($locale)),
            'image' => $cover['url'],
            // Declared alongside the URL rather than hardcoded: a cover that
            // predates the JPEG variant falls back to the 1600x900 WebP, and
            // advertising 1200x630 for it makes unfurlers crop or reject.
            'width' => $cover['width'],
            'height' => $cover['height'],
            'type' => 'article',
        ];
    }

    /**
     * The JPEG built for unfurlers, falling back to the landscape WebP for
     * events whose cover predates that variant.
     *
     * @return array{url: string|null, width: int|null, height: int|null}
     */
    private static function cover(Event $event): array
    {
        $variants = $event->cover_variants ?? [];

        if (isset($variants['og'])) {
            return ['url' => url('/storage/'.$variants['og']), 'width' => 1200, 'height' => 630];
        }

        if (isset($variants['landscape'])) {
            return ['url' => url('/storage/'.$variants['landscape']), 'width' => 1600, 'height' => 900];
        }

        return ['url' => null, 'width' => null, 'height' => null];
    }

    private static function trim(?string $text): ?string
    {
        $text = trim(strip_tags((string) $text));

        return $text === '' ? null : Str::limit($text, 160);
    }
}
