<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\PosterPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The poster workshop.
 *
 * The server's only job here is writing the prompt. Laying the title and the
 * code over the artwork happens in the browser on a canvas, because that is
 * where Arabic gets shaped correctly -- this machine's ImageMagick lists a
 * Pango delegate that does not actually work, and a server that renders
 * Arabic as disconnected letterforms is worse than one that does not try.
 * The browser also means the owner sees the poster before downloading it.
 */
class PosterController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('owner/poster', [
            'event' => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'place_ar' => $event->place->name_ar,
                'place_en' => $event->place->name_en,
                'qr_url' => route('owner.events.qr', $event),
            ],
            'formats' => collect(PosterPrompt::FORMATS)
                ->map(fn (array $f, string $key) => [
                    'key' => $key, 'width' => $f[0], 'height' => $f[1], 'ratio' => $f[2],
                ])->values()->all(),
            'kinds' => PosterPrompt::KINDS,
            'moods' => PosterPrompt::MOODS,
            'palettes' => collect(PosterPrompt::PALETTES)
                ->map(fn (array $colors, string $key) => ['key' => $key, 'colors' => $colors])
                ->values()->all(),
        ]);
    }

    /**
     * The prompt for one set of choices, rebuilt as the owner changes them.
     */
    public function prompt(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $choices = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', PosterPrompt::KINDS)],
            'mood' => ['required', 'string', 'in:'.implode(',', PosterPrompt::MOODS)],
            'palette' => ['required', 'string', 'in:'.implode(',', array_keys(PosterPrompt::PALETTES))],
            'format' => ['required', 'string', 'in:'.implode(',', array_keys(PosterPrompt::FORMATS))],
            'locale' => ['required', 'string', 'in:ar,en'],
        ]);

        return response()->json(
            PosterPrompt::build($event, $choices, $choices['locale'])
        );
    }
}
