<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('view_roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('manage_roles') && $user->isSuperAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }
}
