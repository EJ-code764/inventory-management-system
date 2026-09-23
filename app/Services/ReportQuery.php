<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportQuery
{
    public const TYPES = [
        'inventory' => 'Inventory Report', 'valuation' => 'Inventory Valuation',
        'movements' => 'Stock Movement Report', 'low-stock' => 'Low Stock Report',
        'expiration' => 'Expiration Report', 'purchases' => 'Purchase Report',
        'adjustments' => 'Stock Adjustment Report', 'transfers' => 'Stock Transfer Report',
    ];

    public const DATES = [
        'movements' => 'inventory_movements.occurred_at', 'expiration' => 'product_batches.expiration_date',
        'purchases' => 'purchase_orders.ordered_at', 'adjustments' => 'stock_adjustments.created_at',
        'transfers' => 'stock_transfers.transfer_date',
    ];

    public const DECIMALS = ['quantity', 'reserved_quantity', 'available_quantity', 'effective_reorder_level', 'valuation',
        'unit_cost', 'quantity_delta', 'quantity_before', 'quantity_after', 'ordered_quantity', 'received_quantity',
        'remaining_quantity', 'subtotal', 'discount', 'tax', 'previous_quantity', 'new_quantity', 'difference'];

    public const MONEY = [
    'valuation',
    'unit_cost',
    'subtotal',
    'discount',
    'tax',
    ];

    /** @return array<string, string> */
    public function columns(string $report): array
    {
        $identity = ['sku' => 'SKU', 'product_name' => 'Product', 'variant_name' => 'Variant', 'warehouse_name' => 'Warehouse', 'unit_name' => 'Unit'];

        return match ($report) {
            'inventory', 'low-stock' => [...$identity, 'quantity' => 'On hand', 'reserved_quantity' => 'Reserved', 'available_quantity' => 'Available', 'effective_reorder_level' => 'Reorder level'],
            'valuation' => [...$identity, 'quantity' => 'On hand', 'unit_cost' => 'Untracked unit cost', 'valuation' => 'Inventory value'],
            'movements' => [...$identity, 'date' => 'Occurred at', 'type' => 'Movement', 'batch_number' => 'Batch', 'quantity_delta' => 'Change', 'quantity_before' => 'Before', 'quantity_after' => 'After', 'actor' => 'User', 'reference_type' => 'Reference type', 'reference_id' => 'Reference ID', 'reason' => 'Reason'],
            'expiration' => [...$identity, 'batch_number' => 'Batch', 'quantity' => 'Quantity', 'unit_cost' => 'Unit cost', 'date' => 'Expiration date'],
            'purchases' => [...$identity, 'number' => 'Purchase number', 'supplier_name' => 'Supplier', 'date' => 'Order date', 'status' => 'Status', 'ordered_quantity' => 'Ordered', 'received_quantity' => 'Received', 'remaining_quantity' => 'Remaining', 'unit_cost' => 'Unit cost', 'discount' => 'Line discount', 'tax' => 'Line tax', 'subtotal' => 'Line subtotal'],
            'adjustments' => [...$identity, 'number' => 'Adjustment', 'date' => 'Recorded at', 'previous_quantity' => 'Previous', 'new_quantity' => 'New', 'difference' => 'Difference', 'reason' => 'Reason', 'actor' => 'User'],
            'transfers' => [...$identity, 'warehouse_name' => 'Source warehouse', 'destination_name' => 'Destination warehouse', 'number' => 'Transfer', 'date' => 'Transfer date', 'status' => 'Status', 'quantity' => 'Quantity', 'actor' => 'Created by'],
        };
    }

    /**
     * Read-only query; callers authorize the selected report and validate filters.
     * All joins are one-to-one except the grouped batch valuation subquery.
     *
     * @param  array<string, string>  $filters
     */
    public function query(string $report, array $filters = []): Builder
    {
        abort_unless(isset(self::TYPES[$report]), 404);
        $dateColumn = self::DATES[$report] ?? null;
        if (in_array($report, ['inventory', 'low-stock'], true)) {
            $query = app(InventoryQuery::class)->overview(lowStock: $report === 'low-stock')->toBase();
            $query->selectRaw('COALESCE(inventories.quantity, 0) - COALESCE(inventories.reserved_quantity, 0) as available_quantity')
                ->selectRaw('COALESCE(inventories.reorder_level, stock_items.reorder_level) as effective_reorder_level');
        } elseif ($report === 'valuation') {
            $batchValues = DB::table('product_batches')->select('stock_item_id', 'warehouse_id')
                ->selectRaw('SUM(quantity) as tracked_quantity, SUM(quantity * unit_cost) as tracked_value')
                ->groupBy('stock_item_id', 'warehouse_id');
            $query = DB::table('inventories')->join('stock_items', 'stock_items.id', '=', 'inventories.stock_item_id')
                ->join('warehouses', 'warehouses.id', '=', 'inventories.warehouse_id')
                ->leftJoinSub($batchValues, 'batch_values', function (JoinClause $join): void {
                    $join->on('batch_values.stock_item_id', '=', 'inventories.stock_item_id')->on('batch_values.warehouse_id', '=', 'inventories.warehouse_id');
                })->select('inventories.id', 'inventories.quantity', 'stock_items.cost_price as unit_cost')
                ->selectRaw('COALESCE(batch_values.tracked_value, 0) + (inventories.quantity - COALESCE(batch_values.tracked_quantity, 0)) * stock_items.cost_price as valuation');
        } else {
            [$table, $parent, $foreign, $warehouse] = match ($report) {
                'movements' => ['inventory_movements', null, null, 'inventory_movements.warehouse_id'],
                'expiration' => ['product_batches', null, null, 'product_batches.warehouse_id'],
                'purchases' => ['purchase_order_items', 'purchase_orders', 'purchase_order_id', 'purchase_orders.warehouse_id'],
                'adjustments' => ['stock_adjustment_items', 'stock_adjustments', 'stock_adjustment_id', 'stock_adjustments.warehouse_id'],
                'transfers' => ['stock_transfer_items', 'stock_transfers', 'stock_transfer_id', 'stock_transfers.source_warehouse_id'],
            };
            $query = DB::table($table)->join('stock_items', 'stock_items.id', '=', $table.'.stock_item_id');
            if ($parent) {
                $query->join($parent, $parent.'.id', '=', $table.'.'.$foreign);
            }
            $query->join('warehouses', 'warehouses.id', '=', $warehouse)->select($table.'.id as report_row_id')->selectRaw($dateColumn.' as date');
            if ($report === 'movements') {
                $query->leftJoin('users as actors', 'actors.id', '=', 'inventory_movements.performed_by')
                    ->leftJoin('product_batches as lots', 'lots.id', '=', 'inventory_movements.product_batch_id')
                    ->addSelect('inventory_movements.type', 'quantity_delta', 'quantity_before', 'quantity_after', 'reason', 'reference_type', 'reference_id', 'actors.name as actor', 'lots.batch_number');
            } elseif ($report === 'expiration') {
                $query->addSelect('product_batches.batch_number', 'product_batches.quantity', 'product_batches.unit_cost')->where('product_batches.quantity', '>', 0);
                $period = $filters['period'] ?? '30';
                if ($period === 'expired') {
                    $query->where($dateColumn, '<', today()->toDateString());
                } elseif (in_array($period, ['7', '30'], true)) {
                    $query->where($dateColumn, '>=', today()->toDateString())->where($dateColumn, '<', today()->addDays((int) $period + 1)->toDateString());
                }
            } elseif ($report === 'purchases') {
                $query->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                    ->addSelect('purchase_orders.number', 'purchase_orders.status', 'suppliers.name as supplier_name', 'ordered_quantity', 'received_quantity', 'purchase_order_items.unit_cost', 'purchase_order_items.subtotal', 'purchase_order_items.discount', 'purchase_order_items.tax')
                    ->selectRaw('ordered_quantity - received_quantity as remaining_quantity');
            } elseif ($report === 'adjustments') {
                $query->join('users as actors', 'actors.id', '=', 'stock_adjustments.created_by')
                    ->addSelect('stock_adjustments.number', 'previous_quantity', 'new_quantity', 'difference', 'reason', 'actors.name as actor');
            } else {
                $query->join('warehouses as destinations', 'destinations.id', '=', 'stock_transfers.destination_warehouse_id')
                    ->join('users as actors', 'actors.id', '=', 'stock_transfers.created_by')
                    ->addSelect('stock_transfers.number', 'stock_transfers.status', 'stock_transfer_items.quantity', 'destinations.name as destination_name', 'actors.name as actor');
            }
        }
        $query->leftJoin('product_variants as variants', 'variants.id', '=', 'stock_items.product_variant_id')
            ->join('products as catalog', 'catalog.id', '=', DB::raw('COALESCE(stock_items.product_id, variants.product_id)'))
            ->join('units as report_units', 'report_units.id', '=', 'catalog.unit_id')
            ->addSelect('stock_items.sku', 'stock_items.id as item_id', 'catalog.id as product_id', 'catalog.name as product_name', 'variants.name as variant_name', 'warehouses.name as warehouse_name', 'report_units.short_name as unit_name');
        if (($filters['warehouse'] ?? '') !== '') {
            $query->where(function (Builder $query) use ($report, $filters): void {
                $query->where('warehouses.id', $filters['warehouse']);
                if ($report === 'transfers') {
                    $query->orWhere('destinations.id', $filters['warehouse']);
                }
            });
        }
        if (($filters['category'] ?? '') !== '') {
            $query->where('catalog.category_id', $filters['category']);
        }
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.trim($filters['search']).'%';
            $query->where(fn (Builder $query): Builder => $query->where('stock_items.sku', 'like', $search)
                ->orWhere('stock_items.barcode', 'like', $search)->orWhere('catalog.name', 'like', $search)->orWhere('variants.name', 'like', $search));
        }
        if ($dateColumn) {
            if (($filters['from'] ?? '') !== '') {
                $query->where($dateColumn, '>=', $filters['from']);
            }
            if (($filters['to'] ?? '') !== '') {
                $query->where($dateColumn, '<', Carbon::parse($filters['to'])->addDay()->toDateString());
            }
        }

        return $query;
    }

    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(4, RoundingMode::HalfUp);
    }

    /** @param array<string, string> $filters */
    public function dashboard(array $filters = []): array
    {
        $valuation = $this->query('valuation', $filters);
        $totals = DB::query()->fromSub($valuation, 'balances')->selectRaw('COALESCE(SUM(quantity), 0) as quantity, COALESCE(SUM(valuation), 0) as valuation')->first();
        $products = Product::query()->when(($filters['category'] ?? '') !== '', fn (EloquentBuilder $query): EloquentBuilder => $query->where('category_id', $filters['category']))->count();
        $lowStock = DB::query()->fromSub($this->query('low-stock', $filters), 'low_stock')->distinct()->count('product_id');
        $expiring = DB::query()->fromSub($this->query('expiration', [...$filters, 'period' => '30']), 'expiring')->distinct()->count('product_id');

        return [
            'cards' => ['Total products' => $products, 'Total inventory quantity' => self::decimal($totals->quantity),
                'Inventory value' => self::decimal($totals->valuation), 'Low-stock products' => $lowStock,
                'Expiring products (30 days)' => $expiring, 'Suppliers' => Supplier::count(), 'Warehouses' => Warehouse::count()],
            'movements' => $this->query('movements', $filters)->orderByDesc('date')->orderByDesc('inventory_movements.id')->limit(10)->get(),
            'purchases' => PurchaseOrder::with(['supplier', 'warehouse'])
                ->when(($filters['warehouse'] ?? '') !== '', fn (EloquentBuilder $query): EloquentBuilder => $query->where('warehouse_id', $filters['warehouse']))
                ->when(($filters['category'] ?? '') !== '', fn (EloquentBuilder $query): EloquentBuilder => $query->whereHas('items.stockItem', fn (EloquentBuilder $item): EloquentBuilder => $item->whereHas('product', fn (EloquentBuilder $product): EloquentBuilder => $product->where('category_id', $filters['category']))
                    ->orWhereHas('variant.product', fn (EloquentBuilder $product): EloquentBuilder => $product->where('category_id', $filters['category']))))
                ->latest('ordered_at')->latest('id')->limit(10)->get(),
        ];
    }
}
