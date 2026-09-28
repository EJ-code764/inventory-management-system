<x-layouts.app title="Stock transfers">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">
                Stock transfers
            </h1>

            <p class="page-description">
                Move stock between warehouses with paired inventory movements.
            </p>
        </div>

        @can('create', \App\Models\StockTransfer::class)
            <a
                href="{{ route('stock-transfers.create') }}"
                class="btn-primary"
            >
                New transfer
            </a>
        @endcan
    </div>

    <livewire:stock-transfer-table />
</x-layouts.app>