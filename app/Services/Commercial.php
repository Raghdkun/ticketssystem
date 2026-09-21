<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommercialOffer;
use App\Models\Event;
use App\Models\EventCommercialSnapshot;
use App\Models\Place;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Offers, orders and the snapshot an event takes of its terms.
 *
 * Every act here writes to the audit log; every acceptance copies the
 * venue's representative onto the row rather than pointing at the venue,
 * because the venue's details may change and the record must not.
 */
final class Commercial
{
    /**
     * The offer in force for a venue, if any.
     */
    public function currentOffer(Place $place): ?CommercialOffer
    {
        return $place->offers()->accepted()->latest('accepted_at')->first();
    }

    /**
     * Put a draft in front of the venue. Its terms freeze here.
     *
     * Any other offer still waiting on this venue is withdrawn: two open
     * offers would leave it unclear which one an acceptance meant.
     */
    public function sendOffer(CommercialOffer $offer, User $actor): CommercialOffer
    {
        return DB::transaction(function () use ($offer, $actor) {
            $offer->place->offers()
                ->where('status', 'sent')
                ->whereKeyNot($offer->id)
                ->get()
                ->each(fn (CommercialOffer $open) => $open->forceFill([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                    'superseded_by_id' => $offer->id,
                ])->save());

            $offer->forceFill([
                'status' => 'sent',
                'content_hash' => $offer->hashContent(),
                'sent_at' => now(),
            ])->save();

            AuditLog::record('commercial_offer_sent', $offer->place->user, [
                'offer' => $offer->id,
                'place' => $offer->place_id,
                'by' => $actor->id,
            ]);

            return $offer;
        });
    }

    /**
     * The venue accepts. The offer before it, if any, is superseded; the
     * event snapshots taken under it are untouched by construction.
     */
    public function acceptOffer(CommercialOffer $offer, User $user, Request $request): CommercialOffer
    {
        return DB::transaction(function () use ($offer, $user, $request) {
            /** @var CommercialOffer $locked */
            $locked = CommercialOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($locked->isAccepted()) {
                return $locked;
            }

            $place = $locked->place;

            $place->offers()->accepted()->get()
                ->each(fn (CommercialOffer $previous) => $previous->forceFill([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                    'superseded_by_id' => $locked->id,
                ])->save());

            $locked->forceFill([
                'status' => 'accepted',
                'accepted_by' => $user->id,
                'accepted_at' => now(),
                ...$this->signature($place, $user, $request),
            ])->save();

            AuditLog::record('commercial_offer_accepted', $user, [
                'offer' => $locked->id,
                'place' => $place->id,
                'hash' => $locked->content_hash,
            ]);

            return $locked;
        });
    }

    public function rejectOffer(CommercialOffer $offer, User $user): CommercialOffer
    {
        $offer->forceFill(['status' => 'rejected', 'rejected_at' => now()])->save();

        AuditLog::record('commercial_offer_rejected', $user, [
            'offer' => $offer->id,
            'place' => $offer->place_id,
        ]);

        return $offer;
    }

    /**
     * Freeze the terms an event goes out under. Once, ever.
     */
    public function snapshotEvent(Event $event, CommercialOffer $offer, User $user, ?string $ip): EventCommercialSnapshot
    {
        return $event->commercialSnapshot ?? EventCommercialSnapshot::create([
            'event_id' => $event->id,
            'commercial_offer_id' => $offer->id,
            ...$offer->figures(),
            'accepted_by' => $user->id,
            'accepted_at' => now(),
            'ip' => $ip,
        ]);
    }

    public function sendOrder(ServiceOrder $order, User $actor): ServiceOrder
    {
        $order->forceFill([
            'status' => 'sent',
            'content_hash' => $order->hashContent(),
            'sent_at' => now(),
        ])->save();

        AuditLog::record('service_order_sent', $order->place->user, [
            'order' => $order->id,
            'place' => $order->place_id,
            'by' => $actor->id,
        ]);

        return $order;
    }

    public function acceptOrder(ServiceOrder $order, User $user, Request $request): ServiceOrder
    {
        return DB::transaction(function () use ($order, $user, $request) {
            /** @var ServiceOrder $locked */
            $locked = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isAccepted()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'accepted',
                'accepted_by' => $user->id,
                'accepted_at' => now(),
                ...$this->signature($locked->place, $user, $request),
            ])->save();

            AuditLog::record('service_order_accepted', $user, [
                'order' => $locked->id,
                'place' => $locked->place_id,
                'hash' => $locked->content_hash,
            ]);

            return $locked;
        });
    }

    /**
     * An administrator moves an accepted order along.
     */
    public function advanceOrder(ServiceOrder $order, string $status, User $actor): ServiceOrder
    {
        $order->forceFill([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : $order->completed_at,
            'cancelled_at' => $status === 'cancelled' ? now() : $order->cancelled_at,
        ])->save();

        AuditLog::record('service_order_status', $order->place->user, [
            'order' => $order->id,
            'status' => $status,
            'by' => $actor->id,
        ]);

        return $order;
    }

    /**
     * Who signed, copied from the venue's legal identity as it stands.
     *
     * @return array<string, string|null>
     */
    private function signature(Place $place, User $user, Request $request): array
    {
        return [
            'representative_name' => $place->representative_name ?? $user->name,
            'representative_role' => $place->representative_role,
            'representative_phone' => $place->representative_phone,
            'acceptance_method' => $user->email_verified_at !== null ? 'clickwrap_verified_account' : 'clickwrap',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 255),
        ];
    }
}
