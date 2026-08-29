<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Builds the prompt an owner hands to an image generator.
 *
 * Two things image models are reliably bad at decide the whole shape of this:
 * they cannot draw a scannable QR code, and they render text poorly -- Arabic
 * script especially, where they produce disconnected letterforms that read as
 * gibberish to anyone who can actually read it.
 *
 * So the prompt asks for *artwork*. The real title, date, venue and QR are
 * composited afterwards in the browser, where the script joins up and the code
 * actually scans. Everything the model is told about the event is context for
 * the imagery, never text to draw.
 */
final class PosterPrompt
{
    /**
     * What the artwork is for, and the pixel size that wants.
     *
     * `cover` is the event page's own hero, which is why it is the one target
     * with no reserved band: nothing gets composited onto it.
     *
     * @var array<string, array{0: int, 1: int, 2: string, 3: bool}>
     */
    public const FORMATS = [
        'poster' => [1080, 1350, '4:5', true],
        'story' => [1080, 1920, '9:16', true],
        'square' => [1080, 1080, '1:1', true],
        'print' => [2480, 3508, 'A4 at 300dpi', true],
        'cover' => [1600, 900, '16:9', false],
    ];

    /** Event kinds, each carrying the imagery that suits it. */
    public const KINDS = [
        'concert', 'theatre', 'film', 'lecture', 'exhibition', 'children',
        'festival', 'private', 'wedding', 'poetry', 'folk', 'religious',
        'sports', 'book_fair', 'workshop', 'graduation', 'comedy', 'classical',
        'bazaar', 'memorial',
    ];

    /** How it should feel. */
    public const MOODS = [
        'elegant', 'bold', 'minimal', 'heritage', 'nocturne', 'warm',
        'festive', 'solemn', 'playful', 'cinematic',
    ];

    /** How it should be made -- the craft, kept separate from the mood. */
    public const STYLES = [
        'risograph', 'screenprint', 'art_deco', 'bauhaus', 'collage',
        'woodcut', 'arabesque', 'brutalist', 'retro_future', 'watercolour',
        'papercut', 'photographic', 'flat_vector', 'engraving',
    ];

    /** Optional graphic devices, any number of them. */
    public const ELEMENTS = [
        'strokes', 'grain', 'geometry', 'arabesque_tile', 'torn_paper',
        'gradient_mesh', 'line_art', 'duotone', 'ink_splatter', 'grid',
    ];

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
        'pomegranate' => ['#7B1E28', '#C6403C', '#E8C87A', '#FBF3E4'],
        'desert' => ['#B4542C', '#E0A96D', '#7A8450', '#F3E9D8'],
        'indigo_night' => ['#12203F', '#2E4A80', '#C9D6EA', '#D9A441'],
        'olive' => ['#4A5D23', '#8A9A5B', '#D9C9A3', '#B7472A'],
        'sunset' => ['#F26B54', '#F5A65B', '#7A3E6E', '#FDEBD2'],
        'mint' => ['#2F8F83', '#8FD6C4', '#F6C8A8', '#FFF8EF'],
        'ink' => ['#0B0B0B', '#F5F5F0', '#D22B2B', '#8A8A85'],
    ];

    /**
     * @param  array{kind: string, mood: string, style: string, palette: string, format: string, elements?: array<int, string>}  $choices
     * @return array{prompt: string, negative: string, width: int, height: int, ratio: string, reserves: bool}
     */
    public static function build(Event $event, array $choices, string $locale): array
    {
        [$width, $height, $ratio, $reserves] = self::FORMATS[$choices['format']] ?? self::FORMATS['poster'];
        $palette = self::PALETTES[$choices['palette']] ?? self::PALETTES['brand'];

        $line = fn (string $key, array $replace = []): string => trim(
            (string) __("poster.{$key}", $replace, $locale)
        );

        $elements = array_values(array_intersect($choices['elements'] ?? [], self::ELEMENTS));
        $comma = $locale === 'ar' ? '، ' : ', ';

        $parts = [
            $line('lead', [
                'kind' => $line('kind.'.$choices['kind']),
                'mood' => $line('mood.'.$choices['mood']),
                'style' => $line('style.'.$choices['style']),
            ]),
            $line('about', [
                'title' => $event->title($locale),
                'venue' => $event->place->name($locale),
                'when' => self::when($event, $locale),
            ]),
            self::subject($event, $line, $locale),
            self::setting($event, $line, $locale),
            // Always present, and specific: "Syrian" pulls a model towards
            // Damascus and Palmyra, which is the wrong governorate entirely.
            $line('region'),
            $line('palette', ['colors' => implode(', ', $palette)]),
            $elements === []
                ? ''
                : $line('elements', [
                    'elements' => implode($comma, array_map(
                        fn (string $e): string => $line('element.'.$e),
                        $elements,
                    )),
                ]),
            $line('composition', ['ratio' => $ratio]),
            $line('craft'),
            // The two instructions the whole approach depends on.
            $line('no_text'),
            $reserves ? $line('reserve_qr') : $line('full_bleed'),
        ];

        return [
            'prompt' => implode("\n\n", array_values(array_filter($parts))),
            'negative' => $line('negative'),
            'width' => $width,
            'height' => $height,
            'ratio' => $ratio,
            'reserves' => $reserves,
        ];
    }

    /**
     * The event's own description, as material for the imagery.
     *
     * Trimmed hard: a model given three paragraphs starts illustrating
     * sentences instead of making one image.
     */
    private static function subject(Event $event, callable $line, string $locale): string
    {
        $description = trim(strip_tags((string) $event->description($locale)));

        return $description === ''
            ? ''
            : $line('subject', ['description' => Str::limit($description, 240)]);
    }

    /** Where it happens, which is often the most evocative thing available. */
    private static function setting(Event $event, callable $line, string $locale): string
    {
        $location = $event->resolvedLocation();

        if ($location === null) {
            return '';
        }

        $landmark = $locale === 'ar' ? $location->landmark_ar : $location->landmark_en;
        $address = $locale === 'ar' ? $location->address_ar : $location->address_en;
        $where = trim((string) ($landmark ?: $address));

        return $where === ''
            ? ''
            : $line('setting', ['where' => $where, 'name' => $location->name($locale)]);
    }

    private static function when(Event $event, string $locale): string
    {
        /** @var string $when */
        $when = $event->starts_at
            ->settings(['locale' => $locale === 'ar' ? 'ar-SY' : 'en-GB'])
            ->isoFormat($locale === 'ar' ? 'D MMMM YYYY، HH:mm' : 'D MMMM YYYY, HH:mm');

        return $when;
    }
}
