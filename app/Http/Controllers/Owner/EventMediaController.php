<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMedia;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;

class EventMediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'kind' => ['required', 'in:image,video'],
            'file' => [
                'required',
                'file',
                $request->input('kind') === 'video'
                    ? 'mimetypes:'.implode(',', MediaLibrary::VIDEO_MIMES)
                    : 'mimetypes:'.implode(',', MediaLibrary::IMAGE_MIMES),
                'max:'.($request->input('kind') === 'video'
                    ? MediaLibrary::VIDEO_MAX_KB
                    : MediaLibrary::IMAGE_MAX_KB),
            ],
        ]);

        $file = $request->file('file');

        try {
            $validated['kind'] === 'video'
                ? $this->library->addVideo($event, $file)
                : $this->library->addImage($event, $file);
        } catch (DecoderException) {
            throw ValidationException::withMessages([
                'file' => __('media.undecodable'),
            ]);
        }

        return back()->with('success', __('media.added'));
    }

    public function destroy(Request $request, Event $event, EventMedia $medium): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_if($medium->event_id !== $event->id, 404);

        // Clear the promo selection first so the FK does not dangle.
        if ($event->promo_video_id === $medium->id) {
            $event->promo_video_id = null;
            $event->save();
        }

        $this->library->delete($medium);

        return back()->with('success', __('media.removed'));
    }

    /**
     * Choose which video plays in place of the cover on the public page.
     */
    public function setPromo(Request $request, Event $event, EventMedia $medium): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_if($medium->event_id !== $event->id, 404);
        abort_unless($medium->type === MediaType::Video, 422, 'Only a video can be the promo.');

        $event->promo_video_id = $event->promo_video_id === $medium->id ? null : $medium->id;
        $event->save();

        return back()->with('success', __('media.promo_updated'));
    }
}
