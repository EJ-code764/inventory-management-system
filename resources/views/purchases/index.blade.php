<x-layouts.app title="Purchasing">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-semibold">Purchase orders</h1><p class="mt-1 text-sm text-slate-600">Plan purchases here. Stock changes only when a receipt is recorded.</p></div>
        @can('create', \App\Models\PurchaseOrder::class)<a href="{{ route('purchase-orders.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-white">New purchase order</a>@endcan
    </div>
    <livewire:purchase-order-table />
</x-layouts.app>
