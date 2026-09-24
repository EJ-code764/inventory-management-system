<x-layouts.app title="Inventory dashboard">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Inventory dashboard</h1>
        <a class="rounded-lg bg-surface-muted px-4 py-2 text-white" href="{{ route('inventory.index') }}">Stock overview</a>
    </div>
    <p class="mb-4 text-sm text-muted">Counts cover all stock items and warehouses, including inactive records and
        items not yet stocked. Low stock means current quantity is at or below the reorder level.</p>
    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        @foreach (['Item / warehouse pairs' => $pairs, 'Low stock (includes zero)' => $lowStock, 'Out of stock' => $outOfStock] as $label => $count)
            <section class="rounded-xl bg-surface p-5 shadow-sm">
                <h2 class="text-sm text-muted">{{ $label }}</h2>
                <p class="mt-2 text-3xl font-semibold">{{ $count }}</p>
            </section>
        @endforeach
    </div>
    <a class="underline" href="{{ route('inventory.index', ['lowStock' => 1]) }}">Review low stock</a>
    @can('view-report', 'dashboard')
        <div class="mt-8"><livewire:operations-dashboard /></div>
    @endcan
    @can('viewAny', App\Models\InventoryMovement::class)
        <h2 class="mb-3 mt-8 text-lg font-semibold">Recent movements</h2>
        <x-inventory-movement-list :movements="$recentMovements" />
        <a class="mt-4 inline-block underline" href="{{ route('inventory.movements') }}">View movement history</a>
    @endcan
</x-layouts.app>
