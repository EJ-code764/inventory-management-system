<?php

namespace App\Livewire;

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryMovementTable extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $stockItemId = null;

    #[Url(as: 'movementSearch')]
    public string $search = '';

    #[Url(as: 'movementWarehouse')]
    public string $warehouse = '';

    #[Url(as: 'movementType')]
    public string $type = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouse', 'type'], true)) {
            $this->resetPage(pageName: 'movementPage');
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', InventoryMovement::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $movements = InventoryMovement::with(['stockItem.product', 'stockItem.variant.product', 'warehouse', 'performedBy'])
            ->when($this->stockItemId !== null, fn (Builder $query): Builder => $query->where('stock_item_id', $this->stockItemId))
            ->when($this->warehouse !== '', fn (Builder $query): Builder => $query->where('warehouse_id', ctype_digit($this->warehouse) ? $this->warehouse : 0))
            ->when($this->type !== '', fn (Builder $query): Builder => $query->where('type', $this->type))
            ->when($search !== '', fn (Builder $query): Builder => $query->whereHas('stockItem', fn (Builder $query): Builder => $query
                ->where('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')
                ->orWhereHas('product', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('variant.product', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(15, pageName: 'movementPage');

        return view('livewire.inventory-movement-table', [
            'movements' => $movements,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'types' => InventoryMovementType::cases(),
        ]);
    }
}
