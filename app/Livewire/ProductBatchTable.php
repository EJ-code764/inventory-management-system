<?php

namespace App\Livewire;

use App\Models\ProductBatch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductBatchTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $period = 'all';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouse', 'period'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', ProductBatch::class);
        abort_unless(in_array($this->period, ['all', '7', '30', 'expired'], true), 422, 'Invalid expiration report.');
        $search = mb_substr(trim($this->search), 0, 255);
        $batches = ProductBatch::with(['stockItem.product', 'stockItem.variant.product', 'warehouse'])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('batch_number', 'like', '%'.$search.'%')
                    ->orWhereHas('stockItem', fn (Builder $item): Builder => $item->where('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('variant.product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%')));
            }))
            ->when($this->warehouse !== '', fn (Builder $query): Builder => $query->where('warehouse_id', $this->warehouse))
            ->when($this->period !== 'all', fn (Builder $query): Builder => $query->where('quantity', '>', 0))
            ->when($this->period === 'expired', fn (Builder $query): Builder => $query->where('expiration_date', '<', today()->format('Y-m-d')))
            ->when(in_array($this->period, ['7', '30'], true), fn (Builder $query): Builder => $query->whereBetween('expiration_date', [today()->format('Y-m-d'), today()->addDays((int) $this->period)->endOfDay()->toDateTimeString()]))
            ->orderByRaw('expiration_date IS NULL')->orderBy('expiration_date')->orderBy('id')->paginate(15);

        return view('livewire.product-batch-table', ['batches' => $batches, 'warehouses' => Warehouse::orderBy('name')->get()]);
    }
}
