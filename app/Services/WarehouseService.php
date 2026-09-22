<?php

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    /** @param array{code?: string, name?: string, address?: ?string, status: string} $data */
    public function save(array $data, ?Warehouse $warehouse = null): Warehouse
    {
        try {
            $warehouse ??= new Warehouse;
            $warehouse->fill(Arr::only($data, ['code', 'name', 'address', 'status']));
            $warehouse->save();

            return $warehouse;
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'The warehouse code or name is already in use.']);
        }
    }
}
