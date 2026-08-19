<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client renders flash messages from a { type, message } payload. If the
 * server shape drifts back to a bare string, every confirmation in the app
 * silently disappears, which is exactly what happened once already.
 */
class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifying_a_ticket_flashes_a_toast_payload(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->for(Place::factory()->for($owner))->create();
        $ticket = Ticket::factory()->for($event)->create();

        $this->actingAs($owner)
            ->post(route('tickets.verify.paid', $ticket))
            ->assertRedirect();

        $this->followRedirects(
            $this->actingAs($owner)->post(route('tickets.verify.cancel', $ticket))
        )->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->whereNot('flash.toast.message', null)
        );
    }

    public function test_banning_an_owner_flashes_a_toast_payload(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();

        $this->followRedirects(
            $this->actingAs($admin)->post(route('admin.owners.ban', $owner))
        )->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->whereNot('flash.toast.message', null)
        );
    }

    public function test_pages_without_a_flash_carry_a_null_toast(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.owners'))
            ->assertInertia(fn ($page) => $page->where('flash.toast', null));
    }

    public function test_the_flash_message_is_localised(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();

        $this->followRedirects(
            $this->actingAs($admin)->post(route('admin.owners.ban', $owner).'?lang=ar')
        )->assertInertia(fn ($page) => $page
            ->where('flash.toast.message', __('admin.banned', [], 'ar'))
        );
    }
}
