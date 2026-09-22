<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\InventoryQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryTable extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $stockItemId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $category = '';

    #[Url]
    public bool $lowStock = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouse', 'category', 'lowStock'], true)) {
            $this->resetPage(pageName: 'stockPage');
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warehouse', 'category', 'lowStock']);
        $this->resetPage(pageName: 'stockPage');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Inventory::class);
        $inventories = app(InventoryQuery::class)->overview($this->search, $this->warehouse, $this->category, $this->lowStock, $this->stockItemId)
            ->orderBy('stock_items.sku')->orderBy('warehouses.code')->paginate(15, pageName: 'stockPage');

        return view('livewire.inventory-table', [
            'inventories' => $inventories,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name', 'status']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
