<?php

namespace App\Policies;

use App\Models\User;

class ProductBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('batches.view');
    }
}
