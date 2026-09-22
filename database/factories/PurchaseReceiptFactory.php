<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseReceipt> */
class PurchaseReceiptFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => 'PR-'.Str::ulid(), 'purchase_order_id' => PurchaseOrder::factory(),
            'warehouse_id' => fn (array $attributes): int => PurchaseOrder::findOrFail($attributes['purchase_order_id'])->warehouse_id,
            'received_by' => User::factory(), 'received_at' => now(), 'idempotency_key' => (string) Str::uuid()];
    }
}
