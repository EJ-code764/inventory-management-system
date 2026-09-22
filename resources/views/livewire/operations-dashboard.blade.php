<div>
    <x-alert />
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div><label for="dashboard-warehouse" class="block text-sm font-medium">Warehouse</label><select id="dashboard-warehouse" wire:model.live="warehouse" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All warehouses</option>@foreach($warehouses as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
        <div><label for="dashboard-category" class="block text-sm font-medium">Category</label><select id="dashboard-category" wire:model.live="category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
    </div>
    <p class="mb-6 text-sm text-slate-600">Current balances, including reserved, expired and inactive stock. Total quantity adds different units and is an operational count, not a physical measurement. Product count follows category but not warehouse; supplier and warehouse counts are global. Low-stock and expiring counts are distinct products, including variant parents.</p>
    @if($data)
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($data['cards'] as $label => $value)<section class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="text-sm text-slate-600">{{ $label }}</h2><p class="mt-2 break-all text-2xl font-semibold">{{ $value }}</p></section>@endforeach</div>
        <p class="mb-6 text-sm text-slate-600">Value uses remaining batches at their recorded cost and untracked balances at current catalog cost. Expiring means today through 30 days inclusive; already-expired stock is available in the expiration report.</p>
        <h2 class="mb-3 text-lg font-semibold">Recent stock movements</h2>
        <div class="mb-8 overflow-x-auto rounded-xl bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Date</th><th class="p-3">Product / SKU</th><th class="p-3">Warehouse</th><th class="p-3">Type</th><th class="p-3">Quantity</th></tr></thead><tbody>
            @forelse($data['movements'] as $movement)<tr class="border-t"><td class="p-3">{{ $movement->date }}</td><td class="p-3">{{ $movement->product_name }} — {{ $movement->sku }}</td><td class="p-3">{{ $movement->warehouse_name }}</td><td class="p-3">{{ $movement->type }}</td><td class="p-3">{{ \App\Services\ReportQuery::decimal($movement->quantity_delta) }}</td></tr>@empty<tr><td colspan="5" class="p-6">No recent movements.</td></tr>@endforelse
        </tbody></table></div>
        <h2 class="mb-3 text-lg font-semibold">Recent purchases</h2>
        <div class="overflow-x-auto rounded-xl bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Purchase</th><th class="p-3">Date</th><th class="p-3">Supplier</th><th class="p-3">Warehouse</th><th class="p-3">Status</th><th class="p-3">Document total</th></tr></thead><tbody>
            @forelse($data['purchases'] as $order)<tr class="border-t"><td class="p-3">{{ $order->number }}</td><td class="p-3">{{ $order->ordered_at?->format('Y-m-d') }}</td><td class="p-3">{{ $order->supplier->name }}</td><td class="p-3">{{ $order->warehouse->name }}</td><td class="p-3">{{ $order->status->value }}</td><td class="p-3">{{ $order->total }}</td></tr>@empty<tr><td colspan="6" class="p-6">No recent purchases.</td></tr>@endforelse
        </tbody></table></div>
        <p class="mt-3 text-xs text-slate-500">Latest 10 movements and 10 purchase documents. Category filters select matching purchase documents; displayed totals remain whole-document totals.</p>
    @endif
    <p wire:loading role="status">Updating dashboard…</p>
</div>
