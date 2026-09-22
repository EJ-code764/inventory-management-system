<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->hasPermission('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->hasPermission('suppliers.delete');
    }
}
