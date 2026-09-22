<div class="space-y-4">
    <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><label for="po-search" class="mb-1 block text-sm font-medium">Search</label><input id="po-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Purchase number or supplier" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="po-status" class="mb-1 block text-sm font-medium">Status</label><select id="po-status" wire:model.live="status" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All statuses</option>@foreach($statuses as $state)<option value="{{ $state->value }}">{{ str_replace('_', ' ', strtoupper($state->value)) }}</option>@endforeach</select></div>
        <div><label for="po-supplier" class="mb-1 block text-sm font-medium">Supplier</label><select id="po-supplier" wire:model.live="supplier" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All suppliers</option>@foreach($suppliers as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></div>
        <div><label for="po-warehouse" class="mb-1 block text-sm font-medium">Warehouse</label><select id="po-warehouse" wire:model.live="warehouse" class="w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All warehouses</option>@foreach($warehouses as $row)<option value="{{ $row->id }}">{{ $row->name }}</option>@endforeach</select></div>
        <button type="button" wire:click="clearFilters" class="text-left text-sm underline">Clear filters</button>
    </div>
    <div wire:loading role="status" class="text-sm">Loading orders…</div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>@foreach(['Number', 'Supplier', 'Warehouse', 'Order date', 'Status', 'Total'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($orders as $order)<tr wire:key="order-{{ $order->id }}" class="border-t"><td class="p-4"><a href="{{ route('purchase-orders.show', $order) }}" class="font-medium underline">{{ $order->number }}</a></td><td class="p-4">{{ $order->supplier->name }}</td><td class="p-4">{{ $order->warehouse->code }}</td><td class="p-4">{{ $order->ordered_at?->format('Y-m-d') ?? '—' }}</td><td class="p-4">{{ str_replace('_', ' ', strtoupper($order->status->value)) }}</td><td class="p-4 tabular-nums">{{ $order->total }}</td></tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No purchase orders match your filters.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $orders->links() }}
</div>
