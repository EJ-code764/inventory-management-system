<x-layouts.app :title="'Inventory: '.$stockItem->sku">
    <a class="text-sm underline" href="{{ route('inventory.index') }}">Back to stock overview</a>
    <h1 class="mt-4 text-2xl font-semibold">{{ $stockItem->product?->name ?? $stockItem->variant?->product?->name ?? 'Stock item' }}</h1>
    <p class="mt-1 text-slate-600">{{ $stockItem->sku }} @if($stockItem->variant) · {{ $stockItem->variant->name }} @endif</p>
    <p class="my-4 text-sm text-slate-500">Default reorder level: {{ $stockItem->reorder_level }}. Warehouse overrides take precedence. Available quantity excludes reservations.</p>
    <livewire:inventory-table :stock-item-id="$stockItem->id" />
    @can('viewAny', App\Models\InventoryMovement::class)
        <h2 class="mb-4 mt-8 text-lg font-semibold">Movement history</h2>
        <livewire:inventory-movement-table :stock-item-id="$stockItem->id" />
    @endcan
</x-layouts.app>
