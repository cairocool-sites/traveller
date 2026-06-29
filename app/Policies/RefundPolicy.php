<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_refunds');
    }

    public function view(User $user, Refund $refund): bool
    {
        return $user->hasPermissionTo('view_refunds');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_refunds');
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $user->hasPermissionTo('approve_refunds');
    }

    public function complete(User $user, Refund $refund): bool
    {
        return $user->hasPermissionTo('complete_refunds');
    }

    public function reject(User $user, Refund $refund): bool
    {
        return $user->hasPermissionTo('reject_refunds');
    }

    public function update(User $user, Refund $refund): bool
    {
        return $user->hasAnyPermission(['approve_refunds', 'complete_refunds', 'reject_refunds']);
    }

    public function delete(User $user, Refund $refund): bool
    {
        return false;
    }
}
