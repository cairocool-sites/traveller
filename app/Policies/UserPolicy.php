<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('view_users');
    }

    public function create(User $user): bool
    {
        return $user->can('create_users');
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->can('update_users')) {
            return false;
        }

        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }

    public function deactivate(User $user, User $model): bool
    {
        if (! $user->can('deactivate_users')) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if ($model->is_active && $model->isSuperAdmin()) {
            return User::role('super_admin')
                ->whereKeyNot($model->getKey())
                ->where('is_active', true)
                ->exists();
        }

        return true;
    }

    public function assignRoles(User $user, User $model): bool
    {
        if (! $user->can('assign_roles')) {
            return false;
        }

        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
