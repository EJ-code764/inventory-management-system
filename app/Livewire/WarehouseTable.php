<?php

namespace App\Livewire;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WarehouseTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Warehouse::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $warehouses = Warehouse::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')->orderBy('id')->paginate(10);

        return view('livewire.warehouse-table', compact('warehouses'));
    }
}
