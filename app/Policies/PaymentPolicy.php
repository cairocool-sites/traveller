<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_payments');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('view_payments');
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('review_payments');
    }

    public function approve(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('approve_payments');
    }

    public function reject(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('reject_payments');
    }

    public function viewEvidence(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('view_payment_evidence');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('submit_manual_payments');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPermissionTo('review_payments');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
