<?php

namespace App\Policies;

use App\Models\RateCheck;
use App\Models\User;

class RateCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_rate_checks');
    }

    public function view(User $user, RateCheck $rateCheck): bool
    {
        return $user->hasPermissionTo('view_rate_checks');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RateCheck $rateCheck): bool
    {
        return false;
    }

    public function delete(User $user, RateCheck $rateCheck): bool
    {
        return false;
    }
}
