<?php

namespace App\Support;

use App\Models\Ticket;

final class TicketPresenter
{
    /**
     * Payload for the holder's own ticket page.
     *
     * @return array<string, mixed>
     */
    public static function forPublicPage(Ticket $ticket): array
    {
        return [
            'token' => $ticket->public_token,
            'full_name' => $ticket->full_name,
            'phone' => $ticket->phone,
            'quantity' => $ticket->quantity,
            'status' => $ticket->status->value,
            'hold_expires_at' => $ticket->hold_expires_at?->toIso8601String(),
            'verified_at' => $ticket->verified_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'qr' => QrCode::svgDataUri(route('tickets.verify', $ticket)),
        ];
    }

    /**
     * Payload for the owner's verification screen. Includes who booked and the
     * event they booked, but never any other ticket's data.
     *
     * @return array<string, mixed>
     */
    public static function forOwner(Ticket $ticket): array
    {
        return [
            'token' => $ticket->public_token,
            'full_name' => $ticket->full_name,
            'phone' => $ticket->phone,
            'quantity' => $ticket->quantity,
            'status' => $ticket->status->value,
            'hold_expires_at' => $ticket->hold_expires_at?->toIso8601String(),
            'verified_at' => $ticket->verified_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'event_title_en' => $ticket->event->title_en,
            'event_title_ar' => $ticket->event->title_ar,
        ];
    }
}
