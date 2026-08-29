<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationImage;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Photos of a location, shown to visitors as a slider.
 *
 * Someone deciding whether to book wants to see the room, and someone
 * navigating to it wants to recognise the door.
 */
class LocationImageController extends Controller
{
    /** A venue does not need more than this, and each one costs a download. */
    private const MAX_IMAGES = 12;

    public function store(Request $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $request->validate([
            'image' => [
                'required', 'file',
                'mimetypes:'.implode(',', MediaLibrary::IMAGE_MIMES),
                'max:'.MediaLibrary::IMAGE_MAX_KB,
            ],
        ]);

        if ($location->images()->count() >= self::MAX_IMAGES) {
            return back()->withErrors([
                'image' => __('ui.location.too_many_images', ['max' => self::MAX_IMAGES]),
            ]);
        }

        // Re-encoded rather than stored as uploaded: it strips EXIF (which can
        // carry the photographer's own GPS) and caps a phone photo that would
        // otherwise be several megabytes on every visitor's connection.
        $encoded = (new ImageManager(new Driver))
            ->decodeBinary((string) file_get_contents($request->file('image')->getRealPath()))
            ->scaleDown(1600, 1600)
            ->encode(new WebpEncoder(quality: 82));

        $path = "locations/{$location->id}/".uniqid('img_').'.webp';
        Storage::disk('public')->put($path, (string) $encoded);

        $location->images()->create([
            'path' => $path,
            'sort' => (int) $location->images()->max('sort') + 1,
        ]);

        return back();
    }

    public function destroy(Location $location, LocationImage $image): RedirectResponse
    {
        $this->authorize('update', $location);

        abort_if($image->location_id !== $location->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back();
    }
}
