<div>
    <div class="mb-5 grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="sm:col-span-2 lg:col-span-1"><label for="product-search" class="mb-1 block text-sm font-medium">Name, SKU or barcode</label><input id="product-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="category-filter" class="mb-1 block text-sm font-medium">Category</label><select id="category-filter" wire:model.live="category" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
        <div><label for="brand-filter" class="mb-1 block text-sm font-medium">Brand</label><select id="brand-filter" wire:model.live="brand" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All brands</option>@foreach($brands as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
        <div><label for="status-filter" class="mb-1 block text-sm font-medium">Status</label><select id="status-filter" wire:model.live="status" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <button type="button" wire:click="clearFilters" class="self-end rounded-lg border border-slate-300 px-3 py-2">Clear filters</button>
    </div>
    <p wire:loading role="status" class="mb-2 text-sm text-slate-500">Updating products…</p>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm" wire:loading.class="opacity-60">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Products</caption>
            <thead class="border-b bg-slate-50"><tr>@foreach(['Product / barcode', 'SKU', 'Category / brand', 'Unit', 'Cost', 'Selling price', 'Reorder level', 'Status', 'Actions'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($products as $product)
                    <tr wire:key="product-{{ $product->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3"><a class="font-medium underline" href="{{ route('products.show', $product) }}">{{ $product->name }}</a><span class="block text-slate-500">{{ $product->stockItem?->barcode }}</span></td>
                        <td class="px-4 py-3">{{ $product->stockItem?->sku ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ $product->category?->name }}<span class="block text-slate-500">{{ $product->brand?->name }}</span></td>
                        <td class="px-4 py-3">{{ $product->unit?->short_name }}</td>
                        <td class="px-4 py-3">{{ $product->stockItem?->cost_price ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ $product->stockItem?->selling_price ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ $product->stockItem?->reorder_level ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($product->status->value) }}</td>
                        <td class="px-4 py-3">@can('update', $product)<a class="underline" href="{{ route('products.edit', $product) }}">Edit</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-8 text-center text-slate-500">No products match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</div>
