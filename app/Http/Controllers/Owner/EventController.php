<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ThemeMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\EventRequest;
use App\Models\Event;
use App\Models\Place;
use App\Services\CoverProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly CoverProcessor $covers) {}

    public function index(Request $request): Response
    {
        $place = $this->place($request);

        $events = $place->events()
            ->withCount('tickets')
            ->latest()
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'status' => $event->status->value,
                'starts_at' => $event->starts_at->toIso8601String(),
                'total_quantity' => $event->total_quantity,
                'seats_taken' => $event->seatsTaken(),
                'tickets_count' => $event->tickets_count,
                'cover' => $event->cover_variants['thumb'] ?? null,
                'primary_color' => $event->primary_color,
            ]);

        return Inertia::render('owner/events/index', [
            'place' => ['name_ar' => $place->name_ar, 'name_en' => $place->name_en, 'slug' => $place->slug],
            'events' => $events,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->place($request);

        return Inertia::render('owner/events/create');
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $place = $this->place($request);

        $event = new Event($request->safe()->except(['cover', 'rules']));
        $event->place_id = $place->id;
        $event->slug = $this->uniqueSlug($place, $request->string('title_en')->value());
        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->storeCover($event, $request);

        return to_route('owner.events.index')
            ->with('success', __('events.created'));
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('owner/events/edit', [
            'event' => [
                ...$event->only([
                    'id', 'slug', 'title_ar', 'title_en', 'description_ar', 'description_en',
                    'currency', 'total_quantity', 'max_per_appointment', 'hold_hours',
                    'primary_color', 'secondary_color',
                ]),
                'price' => (float) $event->price,
                'status' => $event->status->value,
                'theme_mode' => $event->theme_mode->value,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                'appointments_close_at' => $event->appointments_close_at->format('Y-m-d\TH:i'),
                'cover' => $event->cover_variants['landscape'] ?? null,
                'rules' => $event->rules->map->only(['body_ar', 'body_en'])->all(),
            ],
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->fill($request->safe()->except(['cover', 'rules']));

        // Clearing manual colours lets the next upload re-derive them.
        if ($event->theme_mode === ThemeMode::Auto) {
            $event->primary_color = $event->primary_color ?: null;
        }

        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->storeCover($event, $request);

        return to_route('owner.events.index')
            ->with('success', __('events.updated'));
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return to_route('owner.events.index')
            ->with('success', __('events.deleted'));
    }

    private function place(Request $request): Place
    {
        return $request->user()->places()->firstOrFail();
    }

    private function storeCover(Event $event, EventRequest $request): void
    {
        $cover = $request->file('cover');

        if ($cover === null) {
            return;
        }

        $contents = file_get_contents($cover->getRealPath());

        if ($contents !== false) {
            $this->covers->process($event, $contents);
        }
    }

    /**
     * @param  array<int, array{body_ar: string, body_en: string}>  $rules
     */
    private function syncRules(Event $event, array $rules): void
    {
        $event->rules()->delete();

        foreach (array_values($rules) as $sort => $rule) {
            $event->rules()->create([
                'body_ar' => $rule['body_ar'],
                'body_en' => $rule['body_en'],
                'sort' => $sort,
            ]);
        }
    }

    private function uniqueSlug(Place $place, string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;

        while ($place->events()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
