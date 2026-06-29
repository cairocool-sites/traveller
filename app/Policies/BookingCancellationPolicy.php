<?php

namespace App\Policies;

use App\Models\BookingCancellation;
use App\Models\User;

class BookingCancellationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_cancellations');
    }

    public function view(User $user, BookingCancellation $bookingCancellation): bool
    {
        return $user->hasPermissionTo('view_cancellations');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('request_cancellations');
    }

    public function review(User $user, BookingCancellation $bookingCancellation): bool
    {
        return $user->hasPermissionTo('review_cancellations');
    }

    public function submitSupplier(User $user, BookingCancellation $bookingCancellation): bool
    {
        return $user->hasPermissionTo('submit_supplier_cancellations');
    }

    public function reconcile(User $user, BookingCancellation $bookingCancellation): bool
    {
        return $user->hasPermissionTo('reconcile_cancellations');
    }

    public function update(User $user, BookingCancellation $bookingCancellation): bool
    {
        return $user->hasPermissionTo('review_cancellations');
    }

    public function delete(User $user, BookingCancellation $bookingCancellation): bool
    {
        return false;
    }
}
