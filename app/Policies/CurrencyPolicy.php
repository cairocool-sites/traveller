<?php

namespace App\Policies;

use App\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_currencies');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermissionTo('view_currencies');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_currencies');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermissionTo('manage_currencies');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return false;
    }
}
