<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\QrCode;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The event's own QR, for posters and flyers.
 *
 * Distinct from a ticket QR: this encodes the public event page, so scanning
 * it starts a booking. A ticket QR encodes an auth-gated verification URL and
 * is never something to print in bulk.
 */
class EventQrController extends Controller
{
    public function __invoke(Event $event): Response
    {
        $this->authorize('update', $event);

        $png = QrCode::png(route('events.show', [$event->place, $event]));

        $name = Str::slug($event->title_en ?: $event->slug) ?: 'event';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'-qr.png"',
            // A poster QR is regenerated from the same URL every time, but the
            // slug can change, so do not let a proxy pin an old filename.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
