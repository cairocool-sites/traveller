<?php

namespace App\Policies;

use App\Models\HotelContact;
use App\Models\User;

class HotelContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function view(User $user, HotelContact $hotelContact): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_hotels');
    }

    public function update(User $user, HotelContact $hotelContact): bool
    {
        return $user->hasPermissionTo('manage_hotels');
    }

    public function delete(User $user, HotelContact $hotelContact): bool
    {
        return false;
    }
}
