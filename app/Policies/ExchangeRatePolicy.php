<?php

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;

class ExchangeRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_exchange_rates');
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->hasPermissionTo('view_exchange_rates');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_exchange_rates');
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->hasPermissionTo('manage_exchange_rates');
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return false;
    }
}
