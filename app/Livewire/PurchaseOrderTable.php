<?php

namespace App\Livewire;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrderTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $supplier = '';

    #[Url]
    public string $warehouse = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'supplier', 'warehouse'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'supplier', 'warehouse']);
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $orders = PurchaseOrder::with(['supplier', 'warehouse'])->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
            $query->where('number', 'like', '%'.$search.'%')->orWhereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('name', 'like', '%'.$search.'%'));
        }))->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->supplier !== '', fn (Builder $query): Builder => $query->where('supplier_id', $this->supplier))
            ->when($this->warehouse !== '', fn (Builder $query): Builder => $query->where('warehouse_id', $this->warehouse))
            ->latest('id')->paginate(10);

        return view('livewire.purchase-order-table', [
            'orders' => $orders, 'statuses' => PurchaseOrderStatus::cases(),
            'suppliers' => Supplier::orderBy('name')->get(), 'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }
}
