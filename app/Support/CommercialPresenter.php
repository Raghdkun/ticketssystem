<?php

namespace App\Support;

use App\Models\CommercialOffer;
use App\Models\EventCommercialSnapshot;
use App\Models\ServiceOrder;

/**
 * One shape for an offer and one for an order, wherever they are shown.
 */
final class CommercialPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function offer(CommercialOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'status' => $offer->isExpired() ? 'expired' : $offer->status,
            'title_ar' => $offer->title_ar,
            'title_en' => $offer->title_en,
            'fee_type' => $offer->fee_type,
            'fee_value' => $offer->fee_value === null ? null : (float) $offer->fee_value,
            'fee_payer' => $offer->fee_payer,
            'settlement_days' => $offer->settlement_days,
            'subscription_amount' => $offer->subscription_amount === null ? null : (float) $offer->subscription_amount,
            'currency' => $offer->currency,
            'included_services_ar' => $offer->included_services_ar,
            'included_services_en' => $offer->included_services_en,
            'additional_terms_ar' => $offer->additional_terms_ar,
            'additional_terms_en' => $offer->additional_terms_en,
            'valid_from' => $offer->valid_from?->toDateString(),
            'valid_until' => $offer->valid_until?->toDateString(),
            'content_hash' => $offer->content_hash,
            'sent_at' => $offer->sent_at?->toIso8601String(),
            'accepted_at' => $offer->accepted_at?->toIso8601String(),
            'rejected_at' => $offer->rejected_at?->toIso8601String(),
            'superseded_at' => $offer->superseded_at?->toIso8601String(),
            'signature' => self::signature($offer->accepted_at === null ? null : [
                'name' => $offer->representative_name,
                'role' => $offer->representative_role,
                'phone' => $offer->representative_phone,
                'method' => $offer->acceptance_method,
                'ip' => $offer->ip,
                'by' => $offer->acceptor?->name,
            ]),
            'place' => [
                'slug' => $offer->place->slug,
                'name_ar' => $offer->place->name_ar,
                'name_en' => $offer->place->name_en,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(ServiceOrder $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'service_type' => $order->service_type,
            'title_ar' => $order->title_ar,
            'title_en' => $order->title_en,
            'description_ar' => $order->description_ar,
            'description_en' => $order->description_en,
            'quantity' => $order->quantity,
            'unit_price' => $order->unit_price === null ? null : (float) $order->unit_price,
            'total' => $order->total(),
            'currency' => $order->currency,
            'terms_ar' => $order->terms_ar,
            'terms_en' => $order->terms_en,
            'content_hash' => $order->content_hash,
            'sent_at' => $order->sent_at?->toIso8601String(),
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'signature' => self::signature($order->accepted_at === null ? null : [
                'name' => $order->representative_name,
                'role' => $order->representative_role,
                'phone' => $order->representative_phone,
                'method' => $order->acceptance_method,
                'ip' => $order->ip,
                'by' => null,
            ]),
            'event' => $order->event === null ? null : [
                'id' => $order->event->id,
                'title_ar' => $order->event->title_ar,
                'title_en' => $order->event->title_en,
            ],
            'place' => [
                'slug' => $order->place->slug,
                'name_ar' => $order->place->name_ar,
                'name_en' => $order->place->name_en,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(EventCommercialSnapshot $snapshot): array
    {
        return [
            'fee_type' => $snapshot->fee_type,
            'fee_value' => $snapshot->fee_value === null ? null : (float) $snapshot->fee_value,
            'fee_payer' => $snapshot->fee_payer,
            'settlement_days' => $snapshot->settlement_days,
            'currency' => $snapshot->currency,
            'accepted_at' => $snapshot->accepted_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, string|null>|null  $signature
     * @return array<string, string|null>|null
     */
    private static function signature(?array $signature): ?array
    {
        return $signature;
    }
}
