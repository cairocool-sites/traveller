<?php

namespace App\Policies;

use App\Models\HotelImage;
use App\Models\User;

class HotelImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function view(User $user, HotelImage $hotelImage): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_hotel_media');
    }

    public function update(User $user, HotelImage $hotelImage): bool
    {
        return $user->hasPermissionTo('manage_hotel_media');
    }

    public function delete(User $user, HotelImage $hotelImage): bool
    {
        return false;
    }
}
