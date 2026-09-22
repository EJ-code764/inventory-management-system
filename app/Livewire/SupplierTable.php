<?php

namespace App\Livewire;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Supplier::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $suppliers = Supplier::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('supplier_code', 'like', '%'.$search.'%')
                    ->orWhere('contact_person', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            }))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->orderBy('name')->orderBy('id')->paginate(10);

        return view('livewire.supplier-table', compact('suppliers'));
    }
}
