<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => 'PO-'.Str::ulid(), 'supplier_id' => Supplier::factory(), 'warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory(), 'status' => PurchaseOrderStatus::Draft, 'ordered_at' => today(), 'revision' => 1,
            'subtotal' => '0.0000', 'discount' => '0.0000', 'tax' => '0.0000', 'total' => '0.0000'];
    }
}
