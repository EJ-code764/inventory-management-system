<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $brand = '';

    #[Url]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'brand', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'category', 'brand', 'status']);
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Product::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $products = Product::query()->with(['stockItem', 'category', 'brand', 'unit'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('stockItem', function (Builder $query) use ($search): void {
                            $query->where('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($this->category !== '', fn (Builder $query): Builder => $query->where('category_id', ctype_digit($this->category) ? $this->category : 0))
            ->when($this->brand !== '', fn (Builder $query): Builder => $query->where('brand_id', ctype_digit($this->brand) ? $this->brand : 0))
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('livewire.product-table', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
