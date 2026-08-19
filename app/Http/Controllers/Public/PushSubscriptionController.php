<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Register a device for updates about one ticket.
     *
     * Authorised by possession of the ticket token, the same secret that grants
     * access to the ticket page itself, since holders have no account.
     */
    public function store(Request $request, Ticket $ticket, PushSender $sender): JsonResponse
    {
        if (! $sender->isConfigured()) {
            return response()->json(['enabled' => false], 200);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $ticket->pushSubscriptions()->updateOrCreate(
            ['fcm_token' => $validated['token']],
            ['locale' => app()->getLocale()],
        );

        return response()->json(['enabled' => true]);
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $token = $request->string('token')->value();

        $ticket->pushSubscriptions()->where('fcm_token', $token)->delete();

        return response()->json(['enabled' => false]);
    }
}
