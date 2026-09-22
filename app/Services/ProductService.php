<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\ProductStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /**
     * @param  array{name: string, description?: ?string, category_id: int|string, brand_id?: int|string|null, unit_id: int|string, sku: string, barcode?: ?string, cost_price: string|int, selling_price: string|int, reorder_level: string|int, status: string}  $data
     */
    public function save(array $data, ?Product $product = null): Product
    {
        try {
            return DB::transaction(function () use ($data, $product): Product {
                $record = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
                if ($record->has_variants) {
                    throw ValidationException::withMessages(['product' => 'Variant products cannot be edited in this module.']);
                }
                if ($record->exists && (string) $record->unit_id !== (string) ($data['unit_id'] ?? null)) {
                    $stockItem = $record->stockItem()->lockForUpdate()->first();
                    if ($stockItem) {
                        foreach (['inventories', 'inventory_movements', 'purchase_order_items', 'purchase_receipt_items', 'product_batches', 'stock_adjustment_items', 'stock_transfer_items'] as $table) {
                            if (DB::table($table)->where('stock_item_id', $stockItem->id)->exists()) {
                                throw ValidationException::withMessages(['unit_id' => 'The unit cannot change after this product has stock or transaction history. Create a separate product for a different unit.']);
                            }
                        }
                    }
                }
                foreach (['category_id' => Category::class, 'brand_id' => Brand::class, 'unit_id' => Unit::class] as $field => $model) {
                    $id = $data[$field] ?? null;
                    if ($id === null && $field === 'brand_id') {
                        continue;
                    }
                    $classification = $model::lockForUpdate()->find($id);
                    if (! $classification || ($classification->status !== 'active' && (string) $record->{$field} !== (string) $id)) {
                        throw ValidationException::withMessages([$field => 'Choose an active '.str_replace('_id', '', $field).'.']);
                    }
                }
                $record->fill(Arr::only($data, ['name', 'description', 'category_id', 'brand_id', 'unit_id', 'status']));
                $record->brand_id = $data['brand_id'] ?? null;
                $record->description = $data['description'] ?? null;
                $record->save();
                $record->stockItem()->updateOrCreate([], [
                    ...Arr::only($data, ['sku', 'cost_price', 'selling_price', 'reorder_level']),
                    'barcode' => $data['barcode'] ?? null,
                    'is_active' => $record->status === ProductStatus::Active,
                ]);

                return $record->load(['stockItem', 'category', 'brand', 'unit']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['sku' => 'The SKU or barcode is already assigned. Use unique values.']);
        }
    }

    public function changeStatus(Product $product, ProductStatus $status): void
    {
        DB::transaction(function () use ($product, $status): void {
            $record = Product::lockForUpdate()->findOrFail($product->id);
            if ($record->has_variants) {
                throw ValidationException::withMessages(['product' => 'Variant products cannot be edited in this module.']);
            }
            if (! $record->stockItem()->exists()) {
                throw ValidationException::withMessages(['product' => 'Edit this product and assign its SKU before changing its status.']);
            }
            $record->update(['status' => $status]);
            $record->stockItem()->firstOrFail()->update(['is_active' => $status === ProductStatus::Active]);
        }, 3);
    }
}
