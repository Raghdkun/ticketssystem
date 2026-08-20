<?php

namespace App\Support;

use App\Enums\MediaType;
use App\Models\Event;
use App\Models\EventMedia;
use App\Models\EventPerk;
use App\Models\EventRule;
use App\Models\Place;
use App\Models\Ticket;

/**
 * Shapes models into the payloads the public Inertia pages consume, so the
 * same shape is guaranteed across the event page and the ticket page.
 */
final class EventPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function place(Place $place): array
    {
        return [
            'slug' => $place->slug,
            'name_ar' => $place->name_ar,
            'name_en' => $place->name_en,
            'whatsapp_number' => $place->whatsapp_number,
            'logo' => $place->logo_path,
        ];
    }

    /**
     * The place's other published, still-open events, for the edge-branding
     * sheet. Excludes the event being viewed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function siblingEvents(Place $place, ?Event $exclude = null): array
    {
        return $place->events()
            ->published()
            ->where('appointments_close_at', '>', now())
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->getKey()))
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (Event $event) => [
                'slug' => $event->slug,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'cover' => $event->cover_variants['thumb'] ?? null,
                'primary_color' => $event->primary_color ?? '#6d28d9',
                'is_free' => $event->isFree(),
                'price' => (float) $event->price,
                'currency' => $event->currency,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forPublicPage(Event $event): array
    {
        return [
            ...self::core($event),
            'seats_remaining' => $event->seatsRemaining(),
            'is_open' => $event->isOpenForAppointments(),
            'max_per_appointment' => $event->max_per_appointment,
            'rules' => $event->rules->map(fn (EventRule $rule) => [
                'id' => $rule->id,
                'body_ar' => $rule->body_ar,
                'body_en' => $rule->body_en,
            ])->all(),
            'perks' => $event->perks->map(fn (EventPerk $perk) => [
                'id' => $perk->id,
                'body_ar' => $perk->body_ar,
                'body_en' => $perk->body_en,
            ])->all(),
            'gallery' => $event->media
                ->where('type', MediaType::Image)
                ->map(fn (EventMedia $m) => ['id' => $m->id, 'path' => $m->path])
                ->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTicket(Ticket $ticket): array
    {
        return [
            ...self::core($ticket->event),
            // The ticket page lists what the holder is entitled to at the
            // door, so the perks travel with it.
            'perks' => $ticket->event->perks->map(fn (EventPerk $perk) => [
                'id' => $perk->id,
                'body_ar' => $perk->body_ar,
                'body_en' => $perk->body_en,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function core(Event $event): array
    {
        return [
            'slug' => $event->slug,
            'title_ar' => $event->title_ar,
            'title_en' => $event->title_en,
            'description_ar' => $event->description_ar,
            'description_en' => $event->description_en,
            'price' => (float) $event->price,
            'currency' => $event->currency,
            'is_free' => $event->isFree(),
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'appointments_close_at' => $event->appointments_close_at->toIso8601String(),
            'cover' => $event->cover_variants,
            'promo_video' => $event->promoVideo === null ? null : [
                'src' => $event->promoVideo->path,
                'poster' => $event->promoVideo->poster_path ?? ($event->cover_variants['landscape'] ?? null),
                'mime' => $event->promoVideo->mime,
            ],
            'theme' => [
                'primary' => $event->primary_color ?? '#6d28d9',
                'secondary' => $event->secondary_color ?? '#db2777',
                'on_primary' => $event->on_primary_color ?? '#ffffff',
            ],
        ];
    }
}
