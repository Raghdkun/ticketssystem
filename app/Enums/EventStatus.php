<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';

    /**
     * The owner has asked for this to go live and an admin has not yet
     * agreed. Deliberately its own state rather than a flag on Draft: the
     * public scope matches Published exactly, so a pending event is invisible
     * by construction rather than by remembering to filter it out.
     */
    case PendingReview = 'pending_review';

    case Published = 'published';
    case Archived = 'archived';

    /** Whether an owner may still edit freely in this state. */
    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }
}
