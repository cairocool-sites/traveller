<?php

namespace App\Policies;

use App\Models\SearchSession;
use App\Models\User;

class SearchSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_search_sessions');
    }

    public function view(User $user, SearchSession $searchSession): bool
    {
        return $user->hasPermissionTo('view_search_sessions');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SearchSession $searchSession): bool
    {
        return false;
    }

    public function delete(User $user, SearchSession $searchSession): bool
    {
        return false;
    }
}
