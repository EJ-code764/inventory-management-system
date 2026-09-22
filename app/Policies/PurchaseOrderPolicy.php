<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.update');
    }

    public function order(User $user, PurchaseOrder $order): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.order');
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.cancel');
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->is_active && $user->hasPermission('purchasing.receive');
    }
}
