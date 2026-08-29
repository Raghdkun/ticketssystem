<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventReportController extends Controller
{
    public function __construct(private readonly EventReport $report) {}

    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('owner/report', [
            'event' => [
                'id' => $event->id,
                'title_ar' => $event->title_ar,
                'title_en' => $event->title_en,
                'starts_at' => $event->starts_at->toIso8601String(),
                'is_free' => $event->isFree(),
            ],
            'report' => $this->report->for($event),
            // Who wanted in and could not get in. There is no mailer, so this
            // list is how a venue reaches people when a seat comes back --
            // and it is the only measure of demand it could not meet.
            'waiting' => $event->watchers()
                ->orderBy('id')
                ->get()
                ->map(fn ($watcher) => [
                    'id' => $watcher->id,
                    'full_name' => $watcher->full_name,
                    'phone' => $watcher->phone,
                    'notified_at' => $watcher->notified_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /**
     * Streamed so a large event does not build the whole file in memory.
     */
    public function csv(Request $request, Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $rows = $this->report->rows($event);
        $filename = 'event-'.$event->id.'-tickets.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // BOM so Excel opens Arabic names as UTF-8 rather than mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Reference', 'Name', 'Phone', 'Seats', 'Arrived',
                'Status', 'Booked at', 'Verified at',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
