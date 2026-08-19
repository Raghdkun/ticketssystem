<?php

namespace Tests\Feature;

use App\Actions\VerifyTicket;
use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EventReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->event = Event::factory()->for(Place::factory()->for($this->owner))
            ->create(['price' => 1000, 'total_quantity' => 100]);
    }

    public function test_it_totals_seats_and_money(): void
    {
        Ticket::factory()->paid()->for($this->event)->create(['quantity' => 4, 'arrived_quantity' => 4]);
        Ticket::factory()->for($this->event)->create(['quantity' => 3]);

        $report = app(EventReport::class)->for($this->event);

        $this->assertSame(7, $report['totals']['seats_booked']);
        $this->assertSame(4, $report['totals']['seats_paid']);
        $this->assertSame(4000.0, $report['money']['collected']);
        $this->assertSame(3000.0, $report['money']['outstanding']);
        $this->assertSame(100000.0, $report['money']['potential']);
    }

    /**
     * Someone who paid for five and brought three still paid for five, so
     * revenue must not follow the arrival count.
     */
    public function test_revenue_follows_seats_paid_not_seats_arrived(): void
    {
        $ticket = Ticket::factory()->for($this->event)->create(['quantity' => 5]);
        app(VerifyTicket::class)->markPaid($ticket, $this->owner, 3);

        $report = app(EventReport::class)->for($this->event->fresh());

        $this->assertSame(5, $report['totals']['seats_paid']);
        $this->assertSame(3, $report['totals']['seats_arrived']);
        $this->assertSame(5000.0, $report['money']['collected']);
        $this->assertSame(60.0, (float) $report['rates']['attendance']);
    }

    public function test_it_counts_unattended_bookings(): void
    {
        $noShow = Ticket::factory()->for($this->event)->create(['quantity' => 2]);
        app(VerifyTicket::class)->markNoShow($noShow, $this->owner);
        Ticket::factory()->cancelled()->for($this->event)->create();

        $report = app(EventReport::class)->for($this->event->fresh());

        $this->assertSame(2, $report['rates']['no_show_bookings']);
    }

    public function test_rates_do_not_divide_by_zero_on_an_empty_event(): void
    {
        $report = app(EventReport::class)->for($this->event);

        $this->assertSame(0, $report['rates']['attendance']);
        $this->assertSame(0.0, (float) $report['rates']['fill']);
    }

    public function test_the_owner_can_open_the_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('owner.events.report', $this->event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('owner/report')->has('report'));
    }

    public function test_a_stranger_cannot_open_the_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('owner.events.report', $this->event))
            ->assertForbidden();
    }

    public function test_the_csv_export_contains_the_tickets(): void
    {
        Ticket::factory()->for($this->event)->create(['full_name' => 'ليلى حداد', 'quantity' => 2]);

        $response = $this->actingAs($this->owner)
            ->get(route('owner.events.report.csv', $this->event));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();

        // A UTF-8 BOM so Excel renders Arabic names rather than mojibake.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('ليلى حداد', $body);
        $this->assertStringContainsString('Reference', $body);
    }

    public function test_a_stranger_cannot_download_the_csv(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('owner.events.report.csv', $this->event))
            ->assertForbidden();
    }
}
