<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\User;

class HotelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function view(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_hotels');
    }

    public function update(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('manage_hotels');
    }

    public function publish(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('publish_hotels');
    }

    public function manageMedia(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('manage_hotel_media');
    }

    public function manageFacilities(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('manage_hotel_facilities');
    }

    public function managePolicies(User $user, Hotel $hotel): bool
    {
        return $user->hasPermissionTo('manage_hotel_policies');
    }

    public function delete(User $user, Hotel $hotel): bool
    {
        return false;
    }
}
