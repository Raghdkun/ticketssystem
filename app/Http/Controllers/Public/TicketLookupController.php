<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class TicketLookupController extends Controller
{
    /**
     * "Find my ticket" by the mobile number used to book.
     *
     * A phone number is guessable, so this deliberately returns only enough to
     * recognise your own booking — masked name, event title, status — and
     * never the token. Opening a ticket still requires its link, which the
     * page restores from localStorage when it is the same device.
     */
    public function __invoke(Request $request): Response
    {
        $input = $request->string('phone')->trim()->value();
        $results = [];
        $searched = false;

        if ($input !== '') {
            $searched = true;
            $results = $this->lookup($input);
        }

        return Inertia::render('public/my-tickets', [
            'phone' => $input,
            'searched' => $searched,
            'results' => $results,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookup(string $input): array
    {
        try {
            $phone = (new PhoneNumber($input, 'SY'))->formatE164();
        } catch (Throwable) {
            // libphonenumber raises several unrelated exception types for
            // malformed input. Any of them simply means "no such booking".
            return [];
        }

        return Ticket::query()
            ->with('event.place')
            ->where('phone', $phone)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Ticket $ticket) => [
                // Enough to recognise the booking, not enough to open it.
                'masked_name' => $this->mask($ticket->full_name),
                'quantity' => $ticket->quantity,
                'status' => $ticket->status->value,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'event_title_ar' => $ticket->event->title_ar,
                'event_title_en' => $ticket->event->title_en,
                'place_name_ar' => $ticket->event->place->name_ar,
                'place_name_en' => $ticket->event->place->name_en,
                'whatsapp_number' => $ticket->event->place->whatsapp_number,
            ])
            ->all();
    }

    /**
     * Show the first character of each word: enough for the owner of the
     * booking to recognise it, not enough to learn a stranger's name.
     */
    private function mask(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => Str::substr($part, 0, 1).str_repeat('•', max(1, Str::length($part) - 1)))
            ->implode(' ');
    }
}
