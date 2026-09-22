<div>
    <div class="mb-5 grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div><label for="stock-search" class="mb-1 block text-sm font-medium">Product, variant, SKU or barcode</label><input id="stock-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="stock-warehouse" class="mb-1 block text-sm font-medium">Warehouse</label><select id="stock-warehouse" wire:model.live="warehouse" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All warehouses</option>@foreach($warehouses as $option)<option value="{{ $option->id }}">{{ $option->name }}{{ $option->status === 'inactive' ? ' (inactive)' : '' }}</option>@endforeach</select></div>
        <div><label for="stock-category" class="mb-1 block text-sm font-medium">Category</label><select id="stock-category" wire:model.live="category" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
        <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="lowStock">Low stock only</label>
        <button type="button" wire:click="clearFilters" class="self-end rounded-lg border border-slate-300 px-3 py-2">Clear filters</button>
    </div>
    <p wire:loading role="status" class="mb-2 text-sm text-slate-500">Updating stock overview…</p>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm" wire:loading.class="opacity-60">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Inventory by warehouse</caption>
            <thead class="border-b bg-slate-50"><tr>@foreach(['Product / SKU', 'Warehouse', 'Unit', 'Current', 'Reserved', 'Available', 'Reorder level', 'Stock status'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($inventories as $inventory)
                    @php($item = $inventory->stockItem)
                    @php($product = $item->product ?? $item->variant?->product)
                    <tr wire:key="stock-{{ $inventory->warehouse_id }}-{{ $inventory->stock_item_id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3"><a class="font-medium underline" href="{{ route('inventory.show', $item) }}">{{ $product?->name ?? 'Stock item' }} @if($item->variant) · {{ $item->variant->name }} @endif</a><span class="block text-slate-500">{{ $item->sku }}{{ ! $item->is_active ? ' (inactive)' : '' }}</span></td>
                        <td class="px-4 py-3">{{ $inventory->warehouse->code }}{{ ! $inventory->warehouse->is_active ? ' (inactive)' : '' }}</td>
                        <td class="px-4 py-3">{{ $product?->unit?->short_name }}</td>
                        <td class="px-4 py-3">{{ $inventory->quantity }}</td>
                        <td class="px-4 py-3">{{ $inventory->reserved_quantity }}</td>
                        <td class="px-4 py-3">{{ $inventory->availableQuantity() }}</td>
                        <td class="px-4 py-3">{{ $inventory->effectiveReorderLevel() }}{{ $inventory->reorder_level !== null ? ' (override)' : '' }}</td>
                        <td class="px-4 py-3">{{ $inventory->stockStatus() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-8 text-center text-slate-500">No inventory matches your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $inventories->links() }}</div>
</div>
