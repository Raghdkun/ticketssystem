<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function update(User $user, Location $location): bool
    {
        return $this->owns($user, $location);
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->owns($user, $location);
    }

    private function owns(User $user, Location $location): bool
    {
        return $user->isSuperAdmin() || $location->place->user_id === $user->id;
    }
}
