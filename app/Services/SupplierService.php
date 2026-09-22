<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /** @param array{supplier_code?: string, name?: string, contact_person?: ?string, phone?: ?string, email?: ?string, address?: ?string, status: string} $data */
    public function save(array $data, ?Supplier $supplier = null): Supplier
    {
        try {
            $supplier ??= new Supplier;
            $supplier->fill(Arr::only($data, ['supplier_code', 'name', 'contact_person', 'phone', 'email', 'address', 'status']));
            $supplier->save();

            return $supplier;
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['supplier_code' => 'The supplier code or name is already in use.']);
        }
    }

    public function delete(Supplier $supplier): void
    {
        try {
            DB::transaction(function () use ($supplier): void {
                $locked = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
                if ($locked->purchaseOrders()->exists()) {
                    throw ValidationException::withMessages(['supplier' => 'This supplier is referenced by purchase orders. Deactivate it instead.']);
                }
                $locked->delete();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                throw ValidationException::withMessages(['supplier' => 'This supplier is in use. Deactivate it instead.']);
            }

            throw $exception;
        }
    }
}
