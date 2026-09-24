<x-layouts.app title="Warehouses">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Warehouses</h1>
        @can('create', App\Models\Warehouse::class)
            <a class="rounded-lg bg-surface-muted px-4 py-2 text-white" href="{{ route('warehouses.create') }}">Add warehouse</a>
        @endcan
    </div>
    <livewire:warehouse-table />
</x-layouts.app>
