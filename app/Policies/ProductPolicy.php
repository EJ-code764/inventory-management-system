<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasPermission('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermission('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_active && ! $product->has_variants && $user->hasPermission('products.update');
    }
}
