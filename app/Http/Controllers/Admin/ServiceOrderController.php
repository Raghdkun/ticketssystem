<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use App\Models\ServiceOrder;
use App\Services\Commercial;
use App\Support\CommercialPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service orders: written, sent, and worked through by an administrator.
 */
class ServiceOrderController extends Controller
{
    public function __construct(private readonly Commercial $commercial) {}

    public function index(Request $request): Response
    {
        $placeFilter = $request->string('place')->value();
        $statusFilter = $request->string('status')->value();

        return Inertia::render('admin/service-orders/index', [
            'orders' => ServiceOrder::query()
                ->with(['place:id,slug,name_ar,name_en', 'event:id,title_ar,title_en'])
                ->when($placeFilter !== '', fn ($query) => $query->whereHas('place', fn ($q) => $q->where('slug', $placeFilter)))
                ->when(in_array($statusFilter, ServiceOrder::STATUSES, true), fn ($query) => $query->where('status', $statusFilter))
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (ServiceOrder $order) => CommercialPresenter::order($order))
                ->all(),
            'places' => $this->places(),
            'filter' => ['place' => $placeFilter, 'status' => $statusFilter],
            'types' => ServiceOrder::TYPES,
            'statuses' => ServiceOrder::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $order = ServiceOrder::create($this->validated($request));
        $order->forceFill(['created_by' => $request->user()->id])->save();

        return to_route('admin.orders.show', $order)->with('success', __('ui.orders.admin.drafted'));
    }

    public function show(ServiceOrder $order): Response
    {
        $order->load(['place', 'event:id,title_ar,title_en']);

        return Inertia::render('admin/service-orders/show', [
            'order' => CommercialPresenter::order($order),
            'places' => $this->places(),
            'events' => $this->events($order->place_id),
            'types' => ServiceOrder::TYPES,
        ]);
    }

    public function update(Request $request, ServiceOrder $order): RedirectResponse
    {
        abort_unless($order->isDraft(), 409, 'A sent order is immutable.');

        $order->update($this->validated($request));

        return back()->with('success', __('ui.orders.admin.saved'));
    }

    public function send(Request $request, ServiceOrder $order): RedirectResponse
    {
        abort_unless($order->isDraft(), 409, 'Only a draft can be sent.');

        $this->commercial->sendOrder($order, $request->user());

        return back()->with('success', __('ui.orders.admin.sent'));
    }

    /**
     * Move an accepted order along: in progress, completed, or cancelled.
     * A sent-but-unaccepted order may only be cancelled.
     */
    public function status(Request $request, ServiceOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed', 'cancelled'])],
        ]);

        $allowed = match ($order->status) {
            'sent' => ['cancelled'],
            'accepted' => ['in_progress', 'completed', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            default => [],
        };

        abort_unless(in_array($validated['status'], $allowed, true), 409, 'That transition is not allowed.');

        $this->commercial->advanceOrder($order, $validated['status'], $request->user());

        return back()->with('success', __('ui.orders.admin.status_saved'));
    }

    public function destroy(ServiceOrder $order): RedirectResponse
    {
        abort_unless($order->isDraft(), 409, 'Only a draft can be deleted.');

        $order->delete();

        return to_route('admin.orders.index')->with('success', __('ui.orders.admin.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $placeId = (int) $request->input('place_id');

        return $request->validate([
            'place_id' => ['required', Rule::exists('places', 'id')],
            // The event, if any, must belong to the same venue.
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('place_id', $placeId)],
            'service_type' => ['required', Rule::in(ServiceOrder::TYPES)],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'terms_ar' => ['nullable', 'string', 'max:20000'],
            'terms_en' => ['nullable', 'string', 'max:20000'],
        ]);
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

    /**
     * The venue's events, for attaching an order to one.
     *
     * @return array<int, array{id: int, title_ar: string, title_en: string}>
     */
    private function events(int $placeId): array
    {
        return Event::query()
            ->where('place_id', $placeId)
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get(['id', 'title_ar', 'title_en'])
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
            ])
            ->values()
            ->all();
    }
}
