<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->is_active && $user->hasPermission('warehouses.update');
    }
}
