<x-layouts.app title="Suppliers">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="page-title">
            Suppliers
        </h1>

        @can('create', App\Models\Supplier::class)
            <a
                class="btn-primary"
                href="{{ route('suppliers.create') }}"
            >
                Add supplier
            </a>
        @endcan
    </div>

    <livewire:supplier-table />
</x-layouts.app>
