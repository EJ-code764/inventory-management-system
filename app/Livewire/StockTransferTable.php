<?php

namespace App\Livewire;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\StockTransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransferTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $destination = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'source', 'destination'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'source', 'destination']);
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', StockTransfer::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $transfers = StockTransfer::with(['sourceWarehouse', 'destinationWarehouse', 'creator'])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', '%'.$search.'%')->orWhere('remarks', 'like', '%'.$search.'%')
                    ->orWhereHas('items.stockItem', fn (Builder $item): Builder => $item->where('sku', 'like', '%'.$search.'%'));
            }))->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($this->source !== '', fn (Builder $query): Builder => $query->where('source_warehouse_id', $this->source))
            ->when($this->destination !== '', fn (Builder $query): Builder => $query->where('destination_warehouse_id', $this->destination))
            ->latest('id')->paginate(10);

        return view('livewire.stock-transfer-table', ['transfers' => $transfers, 'statuses' => StockTransferStatus::cases(), 'warehouses' => Warehouse::orderBy('name')->get()]);
    }
}
