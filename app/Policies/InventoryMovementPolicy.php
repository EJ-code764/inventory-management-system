<?php

namespace App\Policies;

use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('inventory.movements.view');
    }
}
