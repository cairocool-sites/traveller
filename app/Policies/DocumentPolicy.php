<?php

namespace App\Policies;

use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_documents');
    }

    public function view(User $user, object $document): bool
    {
        return $user->hasPermissionTo('view_documents');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['generate_receipts', 'generate_vouchers', 'generate_invoices']);
    }

    public function update(User $user, object $document): bool
    {
        return false;
    }

    public function delete(User $user, object $document): bool
    {
        return false;
    }
}
