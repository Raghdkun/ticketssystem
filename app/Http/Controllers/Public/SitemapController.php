<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * XML sitemap of the publicly indexable pages.
     *
     * Ticket pages are excluded by design: each URL is a bearer token granting
     * access to somebody's name and phone number, and they carry `noindex`.
     * Draft, archived and closed events are excluded because they are not
     * reachable either.
     */
    public function __invoke(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('tickets.lookup'), 'priority' => '0.3', 'changefreq' => 'monthly'],
        ];

        Event::query()
            ->published()
            ->where('appointments_close_at', '>', now())
            ->whereHas('place', fn ($query) => $query->where('is_active', true))
            ->with('place:id,slug')
            ->orderBy('starts_at')
            ->chunk(200, function ($events) use (&$urls) {
                foreach ($events as $event) {
                    $urls[] = [
                        'loc' => route('events.show', [$event->place, $event]),
                        'lastmod' => $event->updated_at?->toAtomString(),
                        'priority' => '0.8',
                        'changefreq' => 'daily',
                    ];
                }
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
