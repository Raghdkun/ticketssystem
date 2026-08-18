<?php

namespace App\Services;

use App\Enums\ThemeMode;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Turns an uploaded cover into the responsive WebP set the mobile UI needs and
 * derives the event's theme colours from it.
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

    public function __construct(private readonly PaletteExtractor $palette) {}

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

        // Tiny inline placeholder so the cover never pops in on a slow connection.
        $variants['placeholder'] = (string) $manager->decodeBinary($contents)
            ->cover(24, 32)
            ->encode(new WebpEncoder(quality: 40))
            ->toDataUri();

        $event->cover_path = "{$directory}/landscape.webp";
        $event->cover_variants = $variants;

        if ($event->theme_mode === ThemeMode::Auto) {
            $palette = $this->palette->extract($contents);
            $event->primary_color = $palette['primary'];
            $event->secondary_color = $palette['secondary'];
            $event->on_primary_color = $palette['on_primary'];
        }

        $event->save();
    }
}
