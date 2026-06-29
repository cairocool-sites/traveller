<?php

namespace App\Policies;

use App\Models\BookingStatusHistory;
use App\Models\User;

class BookingStatusHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_bookings');
    }

    public function view(User $user, BookingStatusHistory $bookingStatusHistory): bool
    {
        return $user->hasPermissionTo('view_bookings');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BookingStatusHistory $bookingStatusHistory): bool
    {
        return false;
    }

    public function delete(User $user, BookingStatusHistory $bookingStatusHistory): bool
    {
        return false;
    }
}
