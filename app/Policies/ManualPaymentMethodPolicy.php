<?php

namespace App\Policies;

use App\Models\ManualPaymentMethod;
use App\Models\User;

class ManualPaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_payment_methods') || $user->hasPermissionTo('view_payments');
    }

    public function view(User $user, ManualPaymentMethod $manualPaymentMethod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_payment_methods');
    }

    public function update(User $user, ManualPaymentMethod $manualPaymentMethod): bool
    {
        return $user->hasPermissionTo('manage_payment_methods');
    }

    public function delete(User $user, ManualPaymentMethod $manualPaymentMethod): bool
    {
        return false;
    }
}
