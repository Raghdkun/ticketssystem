<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    /**
     * Whether the user may verify tickets belonging to this event. This is the
     * gate that stops an end user (who holds the same QR URL as the owner) from
     * marking their own ticket as paid.
     */
    public function verifyTickets(User $user, Event $event): bool
    {
        return $this->owns($user, $event);
    }

    private function owns(User $user, Event $event): bool
    {
        return $user->isSuperAdmin()
            || $event->place->user_id === $user->id;
    }
}
