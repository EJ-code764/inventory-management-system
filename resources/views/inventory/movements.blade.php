<x-layouts.app title="Stock movement history">
    <h1 class="mb-2 text-2xl font-semibold">Stock movement history</h1>
    <p class="mb-6 text-sm text-slate-600">Read-only audit trail. Positive changes add stock; negative changes remove stock. Corrections require a new movement.</p>
    <livewire:inventory-movement-table />
</x-layouts.app>
