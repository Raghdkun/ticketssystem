<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PublishEvent;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every event on the platform, and the queue awaiting sign-off.
 *
 * An administrator could already reach any single event through the owner's
 * own screens by policy; this is the view that does not require knowing which
 * venue to look in first.
 */
class EventReviewController extends Controller
{
    public function index(): Response
    {
        $events = Event::query()
            ->with('place:id,slug,name_ar,name_en')
            ->withCount('tickets')
            // Pending first: it is the only part of this screen with a
            // deadline attached to it.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [EventStatus::PendingReview->value])
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('admin/events', [
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'status' => $event->status->value,
                'starts_at' => $event->starts_at->toIso8601String(),
                'tickets_count' => $event->tickets_count,
                'total_quantity' => $event->total_quantity,
                'place' => [
                    'name_ar' => $event->place->name_ar,
                    'name_en' => $event->place->name_en,
                ],
            ])->all(),
            'pending' => $events->where('status', EventStatus::PendingReview)->count(),
        ]);
    }

    public function decide(Event $event, string $verdict, PublishEvent $publisher): RedirectResponse
    {
        abort_unless(in_array($verdict, ['approve', 'reject'], true), 404);

        // Only an event actually waiting on a decision can receive one, so a
        // stale tab cannot un-publish something that has since gone live.
        abort_unless($event->status === EventStatus::PendingReview, 409);

        $approved = $verdict === 'approve';
        $publisher->decide($event, $approved);

        AuditLog::record($approved ? 'event_approved' : 'event_rejected', $event->place->user, [
            'event_id' => $event->id,
            'title' => $event->title_en,
        ]);

        return back()->with('success', __($approved ? 'ui.review.approved' : 'ui.review.rejected'));
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        AuditLog::record('event_deleted', $event->place->user, [
            'event_id' => $event->id,
            'title' => $event->title_en,
            'tickets' => $event->tickets()->count(),
        ]);

        $event->delete();

        return back()->with('success', __('ui.review.deleted'));
    }
}
