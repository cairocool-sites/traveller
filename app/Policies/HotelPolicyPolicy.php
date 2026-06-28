<?php

namespace App\Policies;

use App\Models\HotelPolicy as HotelPolicyModel;
use App\Models\User;

class HotelPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function view(User $user, HotelPolicyModel $hotelPolicy): bool
    {
        return $user->hasPermissionTo('view_hotels');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_hotel_policies');
    }

    public function update(User $user, HotelPolicyModel $hotelPolicy): bool
    {
        return $user->hasPermissionTo('manage_hotel_policies');
    }

    public function delete(User $user, HotelPolicyModel $hotelPolicy): bool
    {
        return false;
    }
}
