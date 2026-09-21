<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\EventCommercialSnapshot;
use App\Models\Place;
use App\Services\Commercial;
use App\Support\CommercialPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Writing and sending commercial offers, venue by venue.
 *
 * A draft is edited until it is right and sent; from then on its terms are
 * the model's to guard. Changing an arrangement means a new offer, which
 * supersedes the old one when the venue accepts it.
 */
class CommercialOfferController extends Controller
{
    public function __construct(private readonly Commercial $commercial) {}

    public function index(Request $request): Response
    {
        $placeFilter = $request->string('place')->value();

        return Inertia::render('admin/commercial-offers/index', [
            'offers' => CommercialOffer::query()
                ->with(['place:id,slug,name_ar,name_en', 'acceptor:id,name'])
                ->when($placeFilter !== '', fn ($query) => $query->whereHas('place', fn ($q) => $q->where('slug', $placeFilter)))
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (CommercialOffer $offer) => CommercialPresenter::offer($offer))
                ->all(),
            'places' => $this->places(),
            'filter' => $placeFilter,
            'fee_types' => CommercialOffer::FEE_TYPES,
            'fee_payers' => CommercialOffer::FEE_PAYERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $offer = CommercialOffer::create($this->validated($request));
        $offer->forceFill(['created_by' => $request->user()->id])->save();

        return to_route('admin.offers.show', $offer)->with('success', __('ui.commercial.admin.drafted'));
    }

    public function show(CommercialOffer $offer): Response
    {
        return Inertia::render('admin/commercial-offers/show', [
            'offer' => CommercialPresenter::offer($offer->load(['place', 'acceptor:id,name'])),
            'places' => $this->places(),
            'fee_types' => CommercialOffer::FEE_TYPES,
            'fee_payers' => CommercialOffer::FEE_PAYERS,
            'snapshots' => $offer->isAccepted() || $offer->status === 'superseded'
                ? EventCommercialSnapshot::query()
                    ->where('commercial_offer_id', $offer->id)
                    ->with('event:id,title_ar,title_en,starts_at')
                    ->get()
                    ->map(fn ($snapshot) => [
                        'event_id' => $snapshot->event_id,
                        'title_ar' => $snapshot->event->title_ar,
                        'title_en' => $snapshot->event->title_en,
                        'accepted_at' => $snapshot->accepted_at->toIso8601String(),
                    ])->all()
                : [],
        ]);
    }

    public function update(Request $request, CommercialOffer $offer): RedirectResponse
    {
        abort_unless($offer->isDraft(), 409, 'A sent offer is immutable.');

        $offer->update($this->validated($request));

        return back()->with('success', __('ui.commercial.admin.saved'));
    }

    public function send(Request $request, CommercialOffer $offer): RedirectResponse
    {
        abort_unless($offer->isDraft(), 409, 'Only a draft can be sent.');

        $this->commercial->sendOffer($offer, $request->user());

        return back()->with('success', __('ui.commercial.admin.sent'));
    }

    public function destroy(CommercialOffer $offer): RedirectResponse
    {
        abort_unless($offer->isDraft(), 409, 'Only a draft can be deleted.');

        $offer->delete();

        return to_route('admin.offers.index')->with('success', __('ui.commercial.admin.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'place_id' => ['required', Rule::exists('places', 'id')],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'fee_type' => ['required', Rule::in(CommercialOffer::FEE_TYPES)],
            'fee_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'fee_payer' => ['required', Rule::in(CommercialOffer::FEE_PAYERS)],
            'settlement_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'subscription_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'included_services_ar' => ['nullable', 'string', 'max:5000'],
            'included_services_en' => ['nullable', 'string', 'max:5000'],
            'additional_terms_ar' => ['nullable', 'string', 'max:20000'],
            'additional_terms_en' => ['nullable', 'string', 'max:20000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        // A percentage fee is a percentage: 0–100, nothing else makes sense.
        if ($data['fee_type'] === 'percentage' && ($data['fee_value'] ?? null) !== null && (float) $data['fee_value'] > 100) {
            throw ValidationException::withMessages([
                'fee_value' => __('ui.commercial.admin.percentage_range'),
            ]);
        }

        return $data;
    }

    /**
     * @return array<int, array{id: int, slug: string, name_ar: string, name_en: string}>
     */
    private function places(): array
    {
        return Place::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'slug', 'name_ar', 'name_en'])
            ->map(fn (Place $place) => [
                'id' => $place->id,
                'slug' => $place->slug,
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
            ])
            ->values()
            ->all();
    }
}
