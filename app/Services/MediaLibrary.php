<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Event;
use App\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Photos and promo videos attached to an event.
 *
 * Images are re-encoded to WebP, which normalises them and strips anything
 * embedded in the original. Videos cannot be re-encoded without a transcoding
 * pipeline, so they are validated on their sniffed MIME type and stored with a
 * generated name — never the name the browser supplied.
 */
final class MediaLibrary
{
    private const DISK = 'public';

    /** Longest edge for a gallery image, in pixels. */
    private const IMAGE_MAX_EDGE = 1600;

    /** @var array<int, string> */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var array<int, string> */
    public const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    /** Hard ceiling for a promo video, in kilobytes. */
    public const VIDEO_MAX_KB = 102400;

    public const IMAGE_MAX_KB = 8192;

    /**
     * @throws DecoderException when the bytes are not a decodable image.
     */
    public function addImage(Event $event, UploadedFile $file): EventMedia
    {
        $contents = (string) file_get_contents($file->getRealPath());

        // A declared MIME type is not proof the bytes decode: a truncated or
        // hand-crafted file passes `mimetypes:` and then blows up here, so the
        // failure is surfaced as a validation error rather than a 500.
        $encoded = (new ImageManager(new Driver))
            ->decodeBinary($contents)
            ->scaleDown(self::IMAGE_MAX_EDGE, self::IMAGE_MAX_EDGE)
            ->encode(new WebpEncoder(quality: 82));

        $path = $this->directory($event).'/'.Str::uuid()->toString().'.webp';
        Storage::disk(self::DISK)->put($path, (string) $encoded);

        return $event->media()->create([
            'type' => MediaType::Image,
            'path' => $path,
            'mime' => 'image/webp',
            'size_bytes' => Storage::disk(self::DISK)->size($path),
            'sort' => $this->nextSort($event),
        ]);
    }

    public function addVideo(Event $event, UploadedFile $file): EventMedia
    {
        // The extension is attacker-controlled; the sniffed type is not.
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $extension = match ($mime) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };

        $path = $this->directory($event).'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk(self::DISK)->put($path, (string) file_get_contents($file->getRealPath()));

        return $event->media()->create([
            'type' => MediaType::Video,
            'path' => $path,
            'poster_path' => $this->extractPoster($event, $path),
            'mime' => $mime,
            'size_bytes' => Storage::disk(self::DISK)->size($path),
            'sort' => $this->nextSort($event),
        ]);
    }

    public function delete(EventMedia $media): void
    {
        foreach (array_filter([$media->path, $media->poster_path]) as $path) {
            Storage::disk(self::DISK)->delete($path);
        }

        $media->delete();
    }

    /**
     * Grab the first frame as a poster.
     *
     * ffmpeg is not guaranteed on shared hosting, so a failure here is not an
     * error: the player falls back to the event's cover image.
     */
    private function extractPoster(Event $event, string $videoPath): ?string
    {
        $absolute = Storage::disk(self::DISK)->path($videoPath);
        $posterPath = $this->directory($event).'/'.Str::uuid()->toString().'-poster.jpg';
        $posterAbsolute = Storage::disk(self::DISK)->path($posterPath);

        try {
            $process = new Process([
                'ffmpeg', '-y', '-ss', '00:00:01', '-i', $absolute,
                '-frames:v', '1', '-q:v', '4', $posterAbsolute,
            ], timeout: 20);

            $process->run();

            return $process->isSuccessful() && file_exists($posterAbsolute) ? $posterPath : null;
        } catch (ProcessFailedException) {
            return null;
        } catch (\Throwable) {
            // ffmpeg missing entirely.
            return null;
        }
    }

    private function directory(Event $event): string
    {
        return "events/{$event->id}/media";
    }

    private function nextSort(Event $event): int
    {
        return (int) $event->media()->max('sort') + 1;
    }
}
