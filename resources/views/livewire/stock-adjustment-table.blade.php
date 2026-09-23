<div class="space-y-4">
    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
        <div><label for="adjustment-search" class="mb-1 block text-sm font-medium">Search adjustments</label><input
                id="adjustment-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255"
                placeholder="Number, SKU, product or reason" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div><label for="adjustment-warehouse-filter" class="mb-1 block text-sm font-medium">Warehouse</label><select
                id="adjustment-warehouse-filter" wire:model.live="warehouse"
                class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="button" wire:click="clearFilters" class="text-left text-sm underline">Clear filters</button>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @foreach (['Adjustment', 'Warehouse', 'Product / SKU', 'Previous', 'New', 'Difference', 'Reason', 'User / Time'] as $heading)
                        <th class="p-3">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adjustment)
                    @foreach ($adjustment->items as $line)
                        <tr wire:key="adjustment-line-{{ $line->id }}" class="border-t">
                            <td class="p-3"><a href="{{ route('stock-adjustments.show', $adjustment) }}"
                                    class="underline">{{ $adjustment->number }}</a><span
                                    class="block text-xs">{{ strtoupper($adjustment->status->value) }}</span></td>
                            <td class="p-3">{{ $adjustment->warehouse->code }}</td>
                            <td class="p-3"><x-purchase-item-label :item="$line->stockItem" /></td>
                            <td class="p-3">{{ $line->previous_quantity }}</td>
                            <td class="p-3">{{ $line->new_quantity }}</td>
                            <td class="p-3">{{ $line->difference }}</td>
                            <td class="p-3">{{ $line->reason }}</td>
                            <td class="p-3">{{ $adjustment->creator->name }}<span
                                    class="block">{{ $adjustment->created_at?->format('Y-m-d H:i:s') }}</span></td>
                        </tr>
                    @endforeach
                @empty<tr>
                        <td colspan="8" class="p-8 text-center text-slate-500">No stock adjustments match your
                            filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $adjustments->links() }}
</div>
