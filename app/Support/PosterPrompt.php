<?php

namespace App\Support;

use App\Models\Event;

/**
 * Builds the prompt an owner hands to an image generator.
 *
 * Two things image models are reliably bad at decide the whole shape of this:
 * they cannot draw a scannable QR code, and they render text poorly -- Arabic
 * script especially, where they produce disconnected letterforms that read as
 * gibberish to anyone who can actually read it.
 *
 * So the prompt asks for *artwork*: a background that suits this event, with
 * deliberate empty space where the title and the code will go. The real title,
 * date, venue and QR are composited afterwards, where they come out sharp and
 * the code actually scans.
 */
final class PosterPrompt
{
    /**
     * Output targets, with the pixel size each one wants.
     *
     * @var array<string, array{0: int, 1: int, 2: string}>
     */
    public const FORMATS = [
        'poster' => [1080, 1350, '4:5'],
        'story' => [1080, 1920, '9:16'],
        'square' => [1080, 1080, '1:1'],
        'print' => [2480, 3508, 'A4 at 300dpi'],
    ];

    /** Event kinds, each carrying the imagery that suits it. */
    public const KINDS = [
        'concert', 'theatre', 'film', 'lecture',
        'exhibition', 'children', 'festival', 'private',
    ];

    /** Visual directions, deliberately few and clearly distinct. */
    public const MOODS = ['elegant', 'bold', 'minimal', 'heritage', 'nocturne', 'warm'];

    /**
     * Palettes as literal hex, because "warm" means nothing to a model.
     *
     * @var array<string, array<int, string>>
     */
    public const PALETTES = [
        'brand' => ['#0A5C49', '#E8A72B', '#FAF7F2', '#12110E'],
        'basalt' => ['#12110E', '#6E675A', '#E5DCCC', '#C88414'],
        'saffron' => ['#E8A72B', '#8A5A0C', '#FBEBCE', '#191712'],
        'jade' => ['#062E24', '#12876A', '#4FCBA5', '#DDECE5'],
        'monochrome' => ['#111111', '#4A4A4A', '#B4B4B4', '#FFFFFF'],
    ];

    /**
     * @param  array{kind: string, mood: string, palette: string, format: string}  $choices
     * @return array{prompt: string, negative: string, width: int, height: int, ratio: string}
     */
    public static function build(Event $event, array $choices, string $locale): array
    {
        [$width, $height, $ratio] = self::FORMATS[$choices['format']] ?? self::FORMATS['poster'];
        $palette = self::PALETTES[$choices['palette']] ?? self::PALETTES['brand'];

        $line = fn (string $key, array $replace = []) => trim(
            (string) __("poster.{$key}", $replace, $locale)
        );

        $parts = [
            $line('lead', [
                'kind' => $line('kind.'.$choices['kind']),
                'mood' => $line('mood.'.$choices['mood']),
            ]),
            $line('about', [
                'title' => $event->title($locale),
                'venue' => $event->place->name($locale),
                'when' => // ar-SY, not ar: the Levant says أيلول, not سبتمبر, and the rest of
                // the app already speaks that way.
                $event->starts_at->settings(['locale' => $locale === 'ar' ? 'ar-SY' : 'en-GB'])->isoFormat('D MMMM YYYY، HH:mm'),
            ]),
            $line('palette', ['colors' => implode(', ', $palette)]),
            $line('composition', ['ratio' => $ratio]),
            // The two instructions the whole approach depends on.
            $line('no_text'),
            $line('reserve_qr'),
        ];

        return [
            'prompt' => implode("\n\n", array_filter($parts)),
            'negative' => $line('negative'),
            'width' => $width,
            'height' => $height,
            'ratio' => $ratio,
        ];
    }
}
