<?php

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

class PlacePolicy
{
    /**
     * Super admins may act on any place; owners only on their own.
     */
    public function view(User $user, Place $place): bool
    {
        return $this->owns($user, $place);
    }

    public function update(User $user, Place $place): bool
    {
        return $this->owns($user, $place);
    }

    public function delete(User $user, Place $place): bool
    {
        return $user->isSuperAdmin();
    }

    private function owns(User $user, Place $place): bool
    {
        return $user->isSuperAdmin() || $place->user_id === $user->id;
    }
}
