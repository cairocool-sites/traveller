<?php

namespace App\Policies;

use App\Models\SupplierCredential;
use App\Models\User;

class SupplierCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }

    public function view(User $user, SupplierCredential $credential): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }

    public function update(User $user, SupplierCredential $credential): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }

    public function delete(User $user, SupplierCredential $credential): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }
}
