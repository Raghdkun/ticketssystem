<?php

use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// An owner may watch the live appointment feed for a place they own.
Broadcast::channel('place.{placeId}', function (User $user, int $placeId): bool {
    return $user->isSuperAdmin()
        || Place::whereKey($placeId)->where('user_id', $user->id)->exists();
});
