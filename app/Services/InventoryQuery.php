<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class InventoryQuery
{
    /** Include virtual zero balances without writing inventory records. */
    public function overview(string $search = '', string $warehouse = '', string $category = '', bool $lowStock = false, ?int $stockItemId = null): Builder
    {
        $search = mb_substr(trim($search), 0, 255);

        return Inventory::query()->from('stock_items')->crossJoin('warehouses')
            ->leftJoin('inventories', function (JoinClause $join): void {
                $join->on('inventories.stock_item_id', '=', 'stock_items.id')
                    ->on('inventories.warehouse_id', '=', 'warehouses.id');
            })
            ->select(['inventories.id', 'stock_items.id as stock_item_id', 'warehouses.id as warehouse_id', 'inventories.reorder_level'])
            ->selectRaw('COALESCE(inventories.quantity, 0) as quantity, COALESCE(inventories.reserved_quantity, 0) as reserved_quantity')
            ->with(['warehouse', 'stockItem.product.category', 'stockItem.product.unit', 'stockItem.variant.product.category', 'stockItem.variant.product.unit'])
            ->when($stockItemId !== null, fn (Builder $query): Builder => $query->where('stock_items.id', $stockItemId))
            ->when($warehouse !== '', fn (Builder $query): Builder => $query->where('warehouses.id', ctype_digit($warehouse) ? $warehouse : 0))
            ->when($category !== '', fn (Builder $query): Builder => $query->whereIn('stock_items.id', StockItem::select('id')->where(function (Builder $query) use ($category): void {
                $categoryId = ctype_digit($category) ? $category : 0;
                $query->whereHas('product', fn (Builder $query): Builder => $query->where('category_id', $categoryId))
                    ->orWhereHas('variant.product', fn (Builder $query): Builder => $query->where('category_id', $categoryId));
            })))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('stock_items.sku', 'like', '%'.$search.'%')
                    ->orWhere('stock_items.barcode', 'like', '%'.$search.'%')
                    ->orWhereIn('stock_items.id', StockItem::select('id')->where(function (Builder $query) use ($search): void {
                        $query->whereHas('product', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('variant', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('product', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%')));
                    }));
            }))
            ->when($lowStock, fn (Builder $query): Builder => $query->whereRaw('COALESCE(inventories.quantity, 0) <= COALESCE(inventories.reorder_level, stock_items.reorder_level)'));
    }
}
