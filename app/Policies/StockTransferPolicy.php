<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('transfers.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('transfers.create');
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $user->is_active && $user->hasPermission('transfers.update');
    }

    public function complete(User $user, StockTransfer $transfer): bool
    {
        return $user->is_active && $user->hasPermission('transfers.complete');
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $user->is_active && $user->hasPermission('transfers.cancel');
    }
}
