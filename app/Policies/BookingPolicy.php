<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_bookings');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->hasPermissionTo('view_bookings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->hasPermissionTo('manage_booking_status');
    }

    public function delete(User $user, Booking $booking): bool
    {
        return false;
    }

    public function reconcile(User $user, Booking $booking): bool
    {
        return $user->hasPermissionTo('reconcile_bookings');
    }
}
