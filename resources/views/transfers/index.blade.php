<x-layouts.app title="Stock transfers">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">Stock transfers</h1><p class="mt-1 text-sm text-slate-600">Move stock between warehouses with paired inventory movements.</p></div>@can('create', \App\Models\StockTransfer::class)<a href="{{ route('stock-transfers.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-white">New transfer</a>@endcan</div>
    <livewire:stock-transfer-table />
</x-layouts.app>
