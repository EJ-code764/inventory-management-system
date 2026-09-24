<x-layouts.app title="Suppliers">
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Suppliers</h1>
        @can('create', App\Models\Supplier::class)
            <a class="rounded-lg bg-surface-muted px-4 py-2 text-white" href="{{ route('suppliers.create') }}">Add supplier</a>
        @endcan
    </div>
    <livewire:supplier-table />
</x-layouts.app>
