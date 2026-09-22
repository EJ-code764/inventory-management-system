<?php

namespace App\Livewire;

use App\Models\StockAdjustment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockAdjustmentTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouse'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'warehouse']);
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', StockAdjustment::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $adjustments = StockAdjustment::with(['warehouse', 'creator', 'items.stockItem.product', 'items.stockItem.variant.product'])
            ->when($this->warehouse !== '', fn (Builder $query): Builder => $query->where('warehouse_id', $this->warehouse))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('items', fn (Builder $item): Builder => $item->where('reason', 'like', '%'.$search.'%')
                        ->orWhereHas('stockItem', fn (Builder $stock): Builder => $stock->where('sku', 'like', '%'.$search.'%')
                            ->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('variant', fn (Builder $variant): Builder => $variant->where('name', 'like', '%'.$search.'%')->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%')))));
            }))->latest('id')->paginate(10);

        return view('livewire.stock-adjustment-table', ['adjustments' => $adjustments, 'warehouses' => Warehouse::orderBy('name')->get()]);
    }
}
