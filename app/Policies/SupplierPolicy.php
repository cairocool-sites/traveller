<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_suppliers');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('view_suppliers');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage_suppliers');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('manage_suppliers');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('manage_suppliers')
            && ! $supplier->credentials()->exists()
            && ! $supplier->operationLogs()->exists()
            && ! $supplier->idempotencyRecords()->exists();
    }

    public function manageCredentials(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('manage_supplier_credentials');
    }

    public function runHealthCheck(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('run_supplier_health_checks');
    }
}
