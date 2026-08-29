<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

/**
 * Where a requested status actually lands.
 *
 * An owner on the approval tier may draft and edit as much as they like; what
 * an admin signs off is the moment it becomes visible to the public. Asking
 * for Published therefore parks the event in PendingReview instead, and only
 * an admin moves it the last step.
 */
class PublishEvent
{
    public function resolve(User $actor, EventStatus $requested): EventStatus
    {
        if ($requested !== EventStatus::Published) {
            return $requested;
        }

        return $actor->needsPublishApproval()
            ? EventStatus::PendingReview
            : EventStatus::Published;
    }

    /**
     * An admin's verdict on a submitted event.
     */
    public function decide(Event $event, bool $approved): void
    {
        $event->update([
            'status' => $approved ? EventStatus::Published : EventStatus::Draft,
        ]);
    }
}
