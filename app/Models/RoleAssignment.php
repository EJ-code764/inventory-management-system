<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleAssignment extends Pivot
{
    protected $table = 'role_user';

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn (): bool => parent::save($options));
    }

    public function delete(): mixed
    {
        return $this->getConnection()->transaction(fn (): mixed => parent::delete());
    }
}
