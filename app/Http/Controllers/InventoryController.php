<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\StockItem;
use App\Services\InventoryQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function dashboard(InventoryQuery $query): View
    {
        Gate::authorize('viewAny', Inventory::class);
        $pairs = $query->overview()->count();
        $lowStock = $query->overview(lowStock: true)->count();
        $outOfStock = $query->overview()->whereRaw('COALESCE(inventories.quantity, 0) = 0')->count();
        $recentMovements = Gate::allows('viewAny', InventoryMovement::class)
            ? InventoryMovement::with(['stockItem', 'warehouse', 'performedBy'])->orderByDesc('occurred_at')->orderByDesc('id')->limit(10)->get()
            : collect();

        return view('inventory.dashboard', compact('pairs', 'lowStock', 'outOfStock', 'recentMovements'));
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Inventory::class);

        return view('inventory.index');
    }

    public function show(StockItem $stockItem): View
    {
        Gate::authorize('viewAny', Inventory::class);
        $stockItem->load(['product.category', 'product.unit', 'variant.product.category', 'variant.product.unit']);

        return view('inventory.show', compact('stockItem'));
    }

    public function movements(): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        return view('inventory.movements');
    }
}
