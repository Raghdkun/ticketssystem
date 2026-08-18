<?php

namespace App\Support;

/**
 * Small sRGB colour helpers used by the event theming pipeline.
 */
final class Color
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function fromHex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Relative luminance per WCAG 2.1.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function luminance(array $rgb): float
    {
        $channels = array_map(function (int $value): float {
            $c = $value / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * WCAG 2.1 contrast ratio between two colours, from 1 to 21.
     *
     * @param  array{0: int, 1: int, 2: int}  $a
     * @param  array{0: int, 1: int, 2: int}  $b
     */
    public static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Pick black or white text for a background, whichever reads better.
     * Auto-extracted palettes routinely produce unreadable pairings, so this
     * is applied to every extracted colour rather than trusting the source.
     */
    public static function readableForeground(string $backgroundHex): string
    {
        $bg = self::fromHex($backgroundHex);

        return self::contrast($bg, [255, 255, 255]) >= self::contrast($bg, [0, 0, 0])
            ? '#ffffff'
            : '#0a0a0a';
    }
}
