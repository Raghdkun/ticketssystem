<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Builds the link-preview JPEG for covers processed before that variant existed.
 *
 * Without it, every event already in the database keeps sharing as a WebP,
 * which is exactly the format WhatsApp is unreliable with — so the feature
 * would only work for events uploaded after the deploy.
 */
class BackfillOgCovers extends Command
{
    protected $signature = 'covers:backfill-og {--force : Rebuild even where one already exists}';

    protected $description = 'Generate the 1200x630 link-preview JPEG for existing event covers';

    public function handle(): int
    {
        $manager = new ImageManager(new Driver);
        $built = 0;
        $skipped = 0;

        Event::query()
            ->whereNotNull('cover_variants')
            ->chunkById(100, function ($events) use ($manager, &$built, &$skipped) {
                foreach ($events as $event) {
                    $variants = $event->cover_variants ?? [];

                    if (isset($variants['og']) && ! $this->option('force')) {
                        $skipped++;

                        continue;
                    }

                    // Rebuilt from the largest variant on disk, because the
                    // original upload is not kept.
                    $source = $variants['landscape'] ?? $variants['portrait'] ?? null;

                    if ($source === null || ! Storage::disk('public')->exists($source)) {
                        $this->warn("event {$event->id}: no source variant on disk, skipped");
                        $skipped++;

                        continue;
                    }

                    $encoded = $manager
                        ->decodeBinary(Storage::disk('public')->get($source))
                        ->cover(1200, 630)
                        ->encode(new JpegEncoder(quality: 82));

                    $path = "events/{$event->id}/og.jpg";
                    Storage::disk('public')->put($path, (string) $encoded);

                    $event->forceFill([
                        'cover_variants' => [...$variants, 'og' => $path],
                    ])->save();

                    $built++;
                }
            });

        $this->info("Built {$built} preview image(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
