<?php

namespace App\Http\Controllers\Owner;

use App\Actions\PublishEvent;
use App\Actions\RepeatEvent;
use App\Enums\EventStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\EventRequest;
use App\Models\CommercialOffer;
use App\Models\Event;
use App\Models\Location;
use App\Models\Place;
use App\Services\Commercial;
use App\Services\CoverProcessor;
use App\Services\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly CoverProcessor $covers,
        private readonly MediaLibrary $library,
        private readonly Commercial $commercial,
    ) {}

    public function index(Request $request): Response
    {
        $place = $this->place($request);

        // A super admin (or an owner not yet linked to a venue) has no place.
        // Show an explanatory empty state rather than a bare 404.
        if ($place === null) {
            return Inertia::render('owner/events/index', [
                'place' => null,
                'events' => [],
                'counts' => ['all' => 0, 'published' => 0, 'draft' => 0, 'pending_review' => 0, 'archived' => 0],
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
            'pending_review' => $place->events()->where('status', EventStatus::PendingReview)->count(),
            'archived' => $place->events()->where('status', EventStatus::Archived)->count(),
        ];

        $filter = $request->query('status');
        $filter = in_array($filter, ['published', 'draft', 'pending_review', 'archived'], true) ? $filter : 'all';

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
                'is_unlisted' => $event->is_unlisted,
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
            'commercial' => $this->commercialSummary($place, null),
        ]);
    }

    public function store(EventRequest $request, PublishEvent $publisher, RepeatEvent $repeat): RedirectResponse
    {
        $place = $this->place($request);

        abort_if($place === null, 403, 'No venue is linked to this account.');

        $event = new Event($request->eventAttributes());
        $event->place_id = $place->id;
        $this->applyOptions($event, $request);
        // An owner on the approval tier asking to publish gets pending review
        // instead, which the public scope does not match.
        $event->status = $publisher->resolve($request->user(), $event->status);
        $event->slug = $this->uniqueSlug($place, $request->string('title_en')->value());
        $this->assertTermsAcknowledged($event, $request);
        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->syncPerks($event, $request->input('perks', []));
        $this->storeCover($event, $request);
        $this->snapshotTerms($event, $request);

        return $this->afterSave($event, $this->repeatFromForm($event, $request, $repeat));
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('owner/events/edit', [
            'locations' => $this->locationOptions($event->place),
            'commercial' => $this->commercialSummary($event->place, $event),
            'event' => [
                ...$event->only([
                    'id', 'slug', 'title_ar', 'title_en', 'description_ar', 'description_en',
                    'currency', 'total_quantity', 'max_per_appointment', 'hold_hours',
                ]),
                'price' => (float) $event->price,
                'status' => $event->status->value,
                'is_unlisted' => $event->is_unlisted,
                'auto_confirm' => $event->auto_confirm,
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
            // What deleting would take with it. The form words its
            // confirmation from this: an event people hold tickets for is
            // archived, not erased.
            'holders' => $this->holders($event),
        ]);
    }

    public function update(EventRequest $request, Event $event, PublishEvent $publisher, RepeatEvent $repeat): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->fill($request->eventAttributes());
        $this->applyOptions($event, $request);

        // Edits to an event that is already live stay live: the gate is on
        // becoming public, not on every subsequent correction. An owner
        // fixing a typo must not take their own event offline.
        // getRawOriginal, not getOriginal: the latter applies the cast and
        // hands back an enum, so comparing it to a string was always true and
        // every edit knocked a live event back into review.
        if ($event->getRawOriginal('status') !== EventStatus::Published->value) {
            $event->status = $publisher->resolve($request->user(), $event->status);
        }

        $this->assertTermsAcknowledged($event, $request);
        $event->save();

        $this->syncRules($event, $request->input('rules', []));
        $this->syncPerks($event, $request->input('perks', []));
        $this->storeCover($event, $request);
        $this->snapshotTerms($event, $request);

        return $this->afterSave($event, $this->repeatFromForm($event, $request, $repeat));
    }

    /**
     * The commercial terms a paid event would go out under, for the form.
     *
     * Null when the venue has no accepted offer: the form then shows no
     * summary and asks for no acknowledgement. Once an event carries a
     * snapshot the terms are its own, and the form shows those instead.
     *
     * @return array<string, mixed>|null
     */
    private function commercialSummary(Place $place, ?Event $event): ?array
    {
        $snapshot = $event?->commercialSnapshot;

        if ($snapshot !== null) {
            return [
                'frozen' => true,
                'fee_type' => $snapshot->fee_type,
                'fee_value' => $snapshot->fee_value === null ? null : (float) $snapshot->fee_value,
                'fee_payer' => $snapshot->fee_payer,
                'settlement_days' => $snapshot->settlement_days,
                'currency' => $snapshot->currency,
            ];
        }

        $offer = $this->commercial->currentOffer($place);

        if ($offer === null) {
            return null;
        }

        return [
            'frozen' => false,
            'fee_type' => $offer->fee_type,
            'fee_value' => $offer->fee_value === null ? null : (float) $offer->fee_value,
            'fee_payer' => $offer->fee_payer,
            'settlement_days' => $offer->settlement_days,
            'currency' => $offer->currency,
        ];
    }

    /**
     * The offer a paid event leaving draft would be frozen under, or null
     * when there is nothing to freeze: a draft, a free event, an event that
     * already carries its terms, or a venue with no accepted offer.
     */
    private function offerToFreeze(Event $event): ?CommercialOffer
    {
        if ($event->status === EventStatus::Draft || $event->isFree() || ($event->exists && $event->commercialSnapshot !== null)) {
            return null;
        }

        return $this->commercial->currentOffer($event->place ?? Place::findOrFail($event->place_id));
    }

    /**
     * The owner has to tick that they publish under the venue's terms.
     * Checked before anything is saved: a refused submission must leave no
     * event behind.
     */
    private function assertTermsAcknowledged(Event $event, EventRequest $request): void
    {
        if ($this->offerToFreeze($event) !== null && ! $request->boolean('commercial_ack')) {
            throw ValidationException::withMessages([
                'commercial_ack' => __('ui.commercial.ack_required'),
            ]);
        }
    }

    /**
     * Freeze the venue's commercial terms onto a paid event as it leaves
     * draft. Without an accepted offer nothing is recorded.
     */
    private function snapshotTerms(Event $event, EventRequest $request): void
    {
        $offer = $this->offerToFreeze($event);

        if ($offer !== null) {
            $this->commercial->snapshotEvent($event, $offer, $request->user(), $request->ip());
        }
    }

    /**
     * Back to the list, with a dialog that says what happened.
     *
     * "Event created" was the wrong thing to tell an owner whose event was
     * parked for review instead of published: they read it as done, told
     * people to book, and nothing was public. A toast, in any tone, was too
     * easy to miss for a distinction that matters this much, so the list
     * opens a dialog naming the state the event landed in -- and, when it is
     * live, a link to the page people will see.
     */
    private function afterSave(Event $event, int $copies): RedirectResponse
    {
        return to_route('owner.events.index')->with('saved_event', [
            'status' => $event->status->value,
            'title_ar' => $event->title_ar,
            'title_en' => $event->title_en,
            'url' => $event->status === EventStatus::Published
                ? route('events.show', [$event->place, $event])
                : null,
            // By link only: the link above is the whole point, and the
            // dialog must not say it is on the home page.
            'unlisted' => $event->is_unlisted,
            'copies' => $copies,
        ]);
    }

    /**
     * The checkbox options, read explicitly: an unticked box is absent from
     * the request, and filling from the safe set would leave the old value.
     * Confirming on booking only means anything for a free event; the flag
     * is kept regardless so a price set back to zero restores the choice.
     */
    private function applyOptions(Event $event, EventRequest $request): void
    {
        $event->is_unlisted = $request->boolean('is_unlisted');
        $event->auto_confirm = $request->boolean('auto_confirm');

        if ($request->boolean('unlimited')) {
            $event->total_quantity = null;
        }
    }

    /**
     * The repeat options that ride along with the form.
     *
     * @return int How many copies were made.
     */
    private function repeatFromForm(Event $event, EventRequest $request, RepeatEvent $repeat): int
    {
        $cadence = $request->input('repeat_cadence');

        if (! is_string($cadence) || $cadence === '') {
            return 0;
        }

        return $repeat->handle($event, $cadence, (int) $request->input('repeat_count', 1))->count();
    }

    /**
     * Bookings that would be lost with the event: paid, or still held.
     */
    private function holders(Event $event): int
    {
        return $event->tickets()
            ->whereIn('status', [TicketStatus::Paid, TicketStatus::Pending])
            ->count();
    }

    /**
     * Copy this event forward on a cadence.
     *
     * Guarded by the same policy as an edit: making twelve copies of an event
     * is at least as consequential as changing one.
     */
    public function repeat(Request $request, Event $event, RepeatEvent $repeat): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'cadence' => ['required', 'string', 'in:'.implode(',', array_keys(RepeatEvent::CADENCES))],
            'count' => ['required', 'integer', 'min:1', 'max:'.RepeatEvent::MAX_COPIES],
        ]);

        $copies = $repeat->handle($event, $validated['cadence'], (int) $validated['count']);

        return to_route('owner.events.index')
            ->with('success', __('events.repeated', ['count' => $copies->count()]));
    }

    /**
     * Delete an event -- or, when people hold tickets for it, archive it.
     *
     * A ticket is a record on somebody's phone and a line in a report.
     * Deleting the event would take both with it, so an event with paid or
     * still-held bookings is archived instead: off every listing, out of the
     * owner's way, and the tickets stay what they were. Everything else is
     * removed for real, files included.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        if ($this->holders($event) > 0) {
            $event->update(['status' => EventStatus::Archived]);

            return to_route('owner.events.index')
                ->with('warning', __('events.archived_instead'));
        }

        foreach ($event->media as $medium) {
            $this->library->delete($medium);
        }

        $this->covers->remove($event);
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

        // A new file wins over the remove box: uploading is the clearer
        // intent of the two, and the old files go either way.
        if ($cover === null) {
            if ($request->boolean('remove_cover') && $event->cover_path !== null) {
                $this->covers->remove($event);
            }

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
