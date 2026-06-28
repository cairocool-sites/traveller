<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_areas');
    }

    public function view(User $user, Area $area): bool
    {
        return $user->hasPermissionTo('view_areas');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_areas');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->hasPermissionTo('manage_areas');
    }

    public function delete(User $user, Area $area): bool
    {
        return false;
    }
}
