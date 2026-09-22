<x-layouts.app :title="$product->name">
    <a class="text-sm underline" href="{{ route('products.index') }}">Back to products</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
        <div class="flex gap-3">
            @if($product->stockItem)
                @can('viewAny', App\Models\Inventory::class)
                    <a class="rounded-lg border border-slate-300 px-4 py-2" href="{{ route('inventory.show', $product->stockItem) }}">View inventory</a>
                @endcan
            @endif
            @can('update', $product)
                <a class="rounded-lg bg-slate-900 px-4 py-2 text-white" href="{{ route('products.edit', $product) }}">Edit product</a>
            @endcan
            <x-product-status-action :product="$product" />
        </div>
    </div>
    <dl class="grid gap-6 rounded-xl bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
        @foreach(['SKU' => $product->stockItem?->sku, 'Barcode' => $product->stockItem?->barcode, 'Category' => $product->category?->name, 'Brand' => $product->brand?->name, 'Unit' => $product->unit?->name, 'Status' => ucfirst($product->status->value), 'Cost price' => $product->stockItem?->cost_price, 'Selling price' => $product->stockItem?->selling_price, 'Reorder level' => $product->stockItem?->reorder_level] as $label => $value)
            <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium">{{ $value ?? 'Not set' }}</dd></div>
        @endforeach
        <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm text-slate-500">Description</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $product->description ?: 'No description' }}</dd></div>
    </dl>
</x-layouts.app>
