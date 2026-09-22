<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClassificationPolicy
{
    public function viewAny(User $user, string $resource): bool
    {
        return $user->is_active && $user->hasPermission($resource.'.view');
    }

    public function create(User $user, string $resource): bool
    {
        return $user->is_active && $user->hasPermission($resource.'.create');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user, $record->getTable());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->is_active && $user->hasPermission($record->getTable().'.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->is_active && $user->hasPermission($record->getTable().'.delete');
    }
}
