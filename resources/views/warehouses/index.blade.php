<x-layouts.app title="Warehouses">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">
            Warehouses
        </h1>

        @can('create', App\Models\Warehouse::class)
            <a
                class="btn-primary"
                href="{{ route('warehouses.create') }}"
            >
                Add warehouse
            </a>
        @endcan
    </div>

    <livewire:warehouse-table />
</x-layouts.app>