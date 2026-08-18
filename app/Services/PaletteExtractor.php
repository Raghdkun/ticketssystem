<?php

namespace App\Services;

use App\Support\Color;

/**
 * Extracts a two-colour palette from a cover image.
 *
 * Runs server-side once per upload rather than in the browser on every view:
 * the result is cacheable, costs the visitor nothing, and survives SSR.
 */
final class PaletteExtractor
{
    /** Pixels darker or lighter than these are ignored when picking a hue. */
    private const MIN_LUMINANCE = 0.05;

    private const MAX_LUMINANCE = 0.95;

    /** Minimum squared RGB distance for the secondary colour to be distinct. */
    private const MIN_DISTANCE = 4000;

    /**
     * @param  string  $contents  Raw image bytes.
     * @return array{primary: string, secondary: string, on_primary: string}
     */
    public function extract(string $contents): array
    {
        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return $this->fallback();
        }

        $small = imagescale($image, 48, 48);
        imagedestroy($image);

        if ($small === false) {
            return $this->fallback();
        }

        $buckets = $this->histogram($small);
        imagedestroy($small);

        if ($buckets === []) {
            return $this->fallback();
        }

        arsort($buckets);

        $primary = $this->bucketToRgb((int) array_key_first($buckets));
        $secondary = $this->pickSecondary($buckets, $primary);

        $primaryHex = Color::toHex($primary);

        return [
            'primary' => $primaryHex,
            'secondary' => Color::toHex($secondary),
            'on_primary' => Color::readableForeground($primaryHex),
        ];
    }

    /**
     * Quantise to 4 bits per channel and count occurrences, skipping pixels too
     * dark, too light, or too grey to make a usable theme colour.
     *
     * @param  \GdImage  $image
     * @return array<int, int>
     */
    private function histogram($image): array
    {
        $buckets = [];
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $luminance = Color::luminance([$r, $g, $b]);

                if ($luminance < self::MIN_LUMINANCE || $luminance > self::MAX_LUMINANCE) {
                    continue;
                }

                // Skip near-greys: they produce muddy, characterless themes.
                if (max($r, $g, $b) - min($r, $g, $b) < 20) {
                    continue;
                }

                $key = (($r >> 4) << 8) | (($g >> 4) << 4) | ($b >> 4);
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
        }

        return $buckets;
    }

    /**
     * @param  array<int, int>  $buckets
     * @param  array{0: int, 1: int, 2: int}  $primary
     * @return array{0: int, 1: int, 2: int}
     */
    private function pickSecondary(array $buckets, array $primary): array
    {
        foreach (array_keys($buckets) as $key) {
            $candidate = $this->bucketToRgb((int) $key);

            $distance = ($candidate[0] - $primary[0]) ** 2
                + ($candidate[1] - $primary[1]) ** 2
                + ($candidate[2] - $primary[2]) ** 2;

            if ($distance >= self::MIN_DISTANCE) {
                return $candidate;
            }
        }

        // No distinct second colour: darken the primary for a usable gradient.
        return array_map(
            fn (int $channel): int => (int) max(0, $channel * 0.55),
            $primary
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function bucketToRgb(int $key): array
    {
        // Centre of the bucket (+8) rather than its floor, to avoid a dark bias.
        return [
            min(255, ((($key >> 8) & 0xF) << 4) + 8),
            min(255, ((($key >> 4) & 0xF) << 4) + 8),
            min(255, (($key & 0xF) << 4) + 8),
        ];
    }

    /**
     * @return array{primary: string, secondary: string, on_primary: string}
     */
    private function fallback(): array
    {
        return [
            'primary' => '#6d28d9',
            'secondary' => '#db2777',
            'on_primary' => '#ffffff',
        ];
    }
}
