<?php

namespace App\Policies;

use App\Models\SupplierOperationLog;
use App\Models\User;

class SupplierOperationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_supplier_logs');
    }

    public function view(User $user, SupplierOperationLog $log): bool
    {
        return $user->hasPermissionTo('view_supplier_logs');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SupplierOperationLog $log): bool
    {
        return false;
    }

    public function delete(User $user, SupplierOperationLog $log): bool
    {
        return false;
    }

    public function viewSensitive(User $user): bool
    {
        return $user->hasPermissionTo('view_sensitive_supplier_logs');
    }
}
