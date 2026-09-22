<x-layouts.app title="Stock adjustments">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-semibold">Stock adjustments</h1><p class="mt-1 text-sm text-slate-600">Auditable corrections for physical counts, damage, expiration, and other legitimate reasons.</p></div>
        @can('create', \App\Models\StockAdjustment::class)<a href="{{ route('stock-adjustments.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-white">New adjustment</a>@endcan
    </div>
    <livewire:stock-adjustment-table />
</x-layouts.app>
