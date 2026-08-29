<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\EventRequest;
use App\Models\Event;
use App\Models\Location;
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

        // A super admin (or an owner not yet linked to a venue) has no place.
        // Show an explanatory empty state rather than a bare 404.
        if ($place === null) {
            return Inertia::render('owner/events/index', [
                'place' => null,
                'events' => [],
                'counts' => ['all' => 0, 'published' => 0, 'draft' => 0],
                'filter' => 'all',
            ]);
        }

        // Counts describe the whole venue, so they stay stable as the list is
        // filtered -- a tab that renumbered itself when selected would be
        // useless for deciding whether to select it.
        $counts = [
            'all' => $place->events()->count(),
            'published' => $place->events()->where('status', EventStatus::Published)->count(),
            'draft' => $place->events()->where('status', EventStatus::Draft)->count(),
        ];

        $filter = $request->query('status');
        $filter = in_array($filter, ['published', 'draft'], true) ? $filter : 'all';

        $events = $place->events()
            ->when($filter !== 'all', fn ($query) => $query->where('status', $filter))
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
            ]);

        return Inertia::render('owner/events/index', [
            'place' => ['name_ar' => $place->name_ar, 'name_en' => $place->name_en, 'slug' => $place->slug],
            'events' => $events,
            'counts' => $counts,
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): Response
    {
        $place = $this->place($request);

        abort_if($place === null, 403, 'No venue is linked to this account.');

        return Inertia::render('owner/events/create', [
            'locations' => $this->locationOptions($place),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null, 403, 'No venue is linked to this account.');

        $event = new Event($request->safe()->except(['cover', 'rules', 'perks']));
        $event->place_id = $place->id;
        $event->slug = $this->uniqueSlug($place, $request->string('title_en')->value());
        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->syncPerks($event, $request->input('perks', []));
        $this->storeCover($event, $request);

        return to_route('owner.events.index')
            ->with('success', __('events.created'));
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('owner/events/edit', [
            'locations' => $this->locationOptions($event->place),
            'event' => [
                ...$event->only([
                    'id', 'slug', 'title_ar', 'title_en', 'description_ar', 'description_en',
                    'currency', 'total_quantity', 'max_per_appointment', 'hold_hours',
                ]),
                'price' => (float) $event->price,
                'status' => $event->status->value,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                'appointments_close_at' => $event->appointments_close_at->format('Y-m-d\TH:i'),
                'cover' => $event->cover_variants['landscape'] ?? null,
                'rules' => $event->rules->map->only(['body_ar', 'body_en'])->all(),
                'perks' => $event->perks->map->only(['body_ar', 'body_en'])->all(),
                'media' => $event->media->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'path' => $m->path,
                    'poster_path' => $m->poster_path,
                    'is_promo' => $event->promo_video_id === $m->id,
                ])->all(),
            ],
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->fill($request->safe()->except(['cover', 'rules', 'perks']));

        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->syncPerks($event, $request->input('perks', []));
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

    /**
     * @return array<int, array{id: int, name_ar: string, name_en: string, is_primary: bool}>
     */
    private function locationOptions(Place $place): array
    {
        return $place->locations()->get()->map(fn (Location $location) => [
            'id' => $location->id,
            'name_ar' => $location->name_ar,
            'name_en' => $location->name_en,
            'is_primary' => $location->is_primary,
        ])->all();
    }

    private function place(Request $request): ?Place
    {
        return $request->user()->places()->first();
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
     * @param  array<int, array{body_ar: string, body_en: string}>  $perks
     */
    private function syncPerks(Event $event, array $perks): void
    {
        $event->perks()->delete();

        foreach (array_values($perks) as $sort => $perk) {
            $event->perks()->create([
                'body_ar' => $perk['body_ar'],
                'body_en' => $perk['body_en'],
                'sort' => $sort,
            ]);
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
