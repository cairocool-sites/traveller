<?php

namespace App\Policies;

use App\Models\Country;
use App\Models\User;

class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_countries');
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('view_countries');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_countries');
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('manage_countries');
    }

    public function delete(User $user, Country $country): bool
    {
        return false;
    }
}
