<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_bookings_made_with_that_number(): void
    {
        Ticket::factory()->create(['phone' => '+963991234567', 'full_name' => 'Layla Haddad']);
        Ticket::factory()->create(['phone' => '+963997654321']);

        $this->get(route('tickets.lookup', ['phone' => '0991234567']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('public/my-tickets')->has('results', 1));
    }

    /**
     * A phone number is guessable, so a lookup must not hand out the token,
     * which is the only credential the ticket page requires.
     */
    public function test_it_never_returns_the_ticket_token(): void
    {
        $ticket = Ticket::factory()->create(['phone' => '+963991234567']);

        $response = $this->get(route('tickets.lookup', ['phone' => '+963991234567']));

        $response->assertOk();
        $response->assertDontSee($ticket->public_token, false);
    }

    public function test_it_masks_the_booking_name(): void
    {
        Ticket::factory()->create(['phone' => '+963991234567', 'full_name' => 'Layla Haddad']);

        $response = $this->get(route('tickets.lookup', ['phone' => '+963991234567']));

        $response->assertDontSee('Layla Haddad');
        $response->assertInertia(
            fn ($page) => $page->where('results.0.masked_name', 'L•••• H•••••')
        );
    }

    public function test_an_unparseable_number_returns_nothing_rather_than_erroring(): void
    {
        Ticket::factory()->create(['phone' => '+963991234567']);

        $this->get(route('tickets.lookup', ['phone' => 'not-a-number']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('results', 0));
    }

    public function test_it_shows_the_empty_state_before_any_search(): void
    {
        $this->get(route('tickets.lookup'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('searched', false)->has('results', 0));
    }

    public function test_lookups_are_rate_limited(): void
    {
        foreach (range(1, 12) as $i) {
            $this->get(route('tickets.lookup', ['phone' => "09912345{$i}0"]))->assertOk();
        }

        $this->get(route('tickets.lookup', ['phone' => '0991234599']))
            ->assertStatus(429);
    }
}
