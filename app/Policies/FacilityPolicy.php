<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_facilities');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $user->hasPermissionTo('view_facilities');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_facilities');
    }

    public function update(User $user, Facility $facility): bool
    {
        return $user->hasPermissionTo('manage_facilities');
    }

    public function delete(User $user, Facility $facility): bool
    {
        return false;
    }
}
