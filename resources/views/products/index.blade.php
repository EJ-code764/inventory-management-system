<x-layouts.app title="Products">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">
            Products
        </h1>

        @can('create', App\Models\Product::class)
            <a class="btn-primary" href="{{ route('products.create') }}">Add product</a>
        @endcan
    </div>

    <livewire:product-table />
</x-layouts.app>