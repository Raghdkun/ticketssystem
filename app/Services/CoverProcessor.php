<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Turns an uploaded cover into the responsive WebP set the mobile UI needs.
 *
 * Colours are no longer derived from it: one fixed identity carries the whole
 * product, so a cover is artwork rather than a source of theme tokens.
 */
final class CoverProcessor
{
    /**
     * Target sizes keyed by variant name. Portrait serves mobile (the primary
     * surface), landscape serves desktop, thumb serves the owner dashboard.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const VARIANTS = [
        'portrait' => [900, 1200],
        'landscape' => [1600, 900],
        'thumb' => [480, 360],
    ];

    private const DISK = 'public';

    /**
     * Generate variants + palette for an event from raw image bytes.
     */
    public function process(Event $event, string $contents): void
    {
        $manager = new ImageManager(new Driver);
        $directory = "events/{$event->id}";

        Storage::disk(self::DISK)->deleteDirectory($directory);

        $variants = [];

        foreach (self::VARIANTS as $name => [$width, $height]) {
            $encoded = $manager->decodeBinary($contents)
                ->cover($width, $height)
                ->encode(new WebpEncoder(quality: 82));

            $path = "{$directory}/{$name}.webp";
            Storage::disk(self::DISK)->put($path, (string) $encoded);
            $variants[$name] = $path;
        }

        // A JPEG at the size link unfurlers expect. Deliberately not WebP:
        // WhatsApp is how these links actually travel here, and its preview
        // fetcher is unreliable with WebP -- a share that falls back to the
        // app icon defeats the point of having a cover at all.
        $og = $manager->decodeBinary($contents)
            ->cover(1200, 630)
            ->encode(new JpegEncoder(quality: 82));

        $ogPath = "{$directory}/og.jpg";
        Storage::disk(self::DISK)->put($ogPath, (string) $og);
        $variants['og'] = $ogPath;

        // Tiny inline placeholder so the cover never pops in on a slow connection.
        $variants['placeholder'] = (string) $manager->decodeBinary($contents)
            ->cover(24, 32)
            ->encode(new WebpEncoder(quality: 40))
            ->toDataUri();

        $event->cover_path = "{$directory}/landscape.webp";
        $event->cover_variants = $variants;

        $event->save();
    }
}
