<x-layouts.app title="Purchasing">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="page-title">
                Purchase orders
            </h1>

            <p class="page-description">
                Plan purchases here. Stock changes only when a receipt is recorded.
            </p>
        </div>

        @can('create', \App\Models\PurchaseOrder::class)
            <a
                href="{{ route('purchase-orders.create') }}"
                class="btn-primary"
            >
                New purchase order
            </a>
        @endcan
    </div>

    <livewire:purchase-order-table />
</x-layouts.app>