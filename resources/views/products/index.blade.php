<x-layouts.app title="Products">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Products</h1>
        @can('create', App\Models\Product::class)
            <a class="rounded-lg bg-slate-900 px-4 py-2 text-white" href="{{ route('products.create') }}">Add product</a>
        @endcan
    </div>
    <livewire:product-table />
</x-layouts.app>
