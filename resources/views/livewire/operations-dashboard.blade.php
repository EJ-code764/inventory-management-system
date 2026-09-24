<div>
    <x-alert />

    {{-- Filters --}}
    <x-ui.card class="mb-6">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-foreground">
                Dashboard filters
            </h2>

            <p class="mt-1 text-sm text-muted">
                Filter inventory insights by warehouse or category.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">

            <x-ui.select
                name="dashboard-warehouse"
                label="Warehouse"
                wire:model.live="warehouse"
            >
                <option value="">All warehouses</option>

                @foreach ($warehouses as $location)
                    <option value="{{ $location->id }}">
                        {{ $location->name }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.select
                name="dashboard-category"
                label="Category"
                wire:model.live="category"
            >
                <option value="">All categories</option>

                @foreach ($categories as $option)
                    <option value="{{ $option->id }}">
                        {{ $option->name }}
                    </option>
                @endforeach
            </x-ui.select>

        </div>
    </x-ui.card>

    {{-- Dashboard description --}}
    <div class="mb-6 rounded-xl border border-primary-100 bg-primary-50/50 px-4 py-3">
        <p class="text-sm leading-6 text-slate-600">
            Current balances include reserved, expired and inactive stock.
            Total quantity adds different units and is an operational count,
            not a physical measurement. Product count follows category but
            not warehouse; supplier and warehouse counts are global.
            Low-stock and expiring counts are distinct products, including
            variant parents.
        </p>
    </div>

    @if ($data)

        {{-- Summary cards --}}
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            @foreach ($data['cards'] as $label => $value)

                <x-ui.stat-card :label="$label">

                    @if ($label === 'Inventory value')
                        <x-money :amount="$value" />
                    @else
                        {{ $value }}
                    @endif

                </x-ui.stat-card>

            @endforeach

        </div>

        {{-- Valuation explanation --}}
        <p class="mb-8 text-sm leading-6 text-muted">
            Value uses remaining batches at their recorded cost and
            untracked balances at current catalog cost. Expiring means
            today through 30 days inclusive; already-expired stock is
            available in the expiration report.
        </p>

        {{-- Recent Stock Movements --}}
        <section class="mb-8">

            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900">
                    Recent stock movements
                </h2>

                <p class="mt-1 text-sm text-muted">
                    Latest inventory changes recorded across your warehouses.
                </p>
            </div>

            <div class="app-table-container">

                <table class="app-table">

                    <caption class="sr-only">
                        Recent stock movements
                    </caption>

                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product / SKU</th>
                            <th>Warehouse</th>
                            <th>Type</th>
                            <th class="text-right">Quantity</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($data['movements'] as $movement)

                            <tr>

                                <td class="whitespace-nowrap text-muted">
                                    {{ $movement->date }}
                                </td>

                                <td>
                                    <div class="font-medium text-slate-900">
                                        {{ $movement->product_name }}
                                    </div>

                                    <div class="mt-0.5 text-xs text-muted">
                                        {{ $movement->sku }}
                                    </div>
                                </td>

                                <td>
                                    {{ $movement->warehouse_name }}
                                </td>

                                <td>
                                    @php
                                        $movementVariant = match (strtolower($movement->type)) {
                                            'purchase' => 'success',
                                            'adjustment' => 'warning',
                                            'transfer' => 'primary',
                                            'sale' => 'danger',
                                            default => 'neutral',
                                        };
                                    @endphp

                                    <x-ui.badge :variant="$movementVariant">
                                        {{ strtoupper($movement->type) }}
                                    </x-ui.badge>
                                </td>

                                <td
                                    @class([
                                        'text-right font-medium tabular-nums',
                                        'text-emerald-600' => $movement->quantity_delta > 0,
                                        'text-red-600' => $movement->quantity_delta < 0,
                                        'text-slate-600' => $movement->quantity_delta == 0,
                                    ])
                                >
                                    @if ($movement->quantity_delta > 0)
                                        +
                                    @endif

                                    {{ \App\Services\ReportQuery::decimal(
                                        $movement->quantity_delta
                                    ) }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="5"
                                    class="px-4 py-12 text-center"
                                >
                                    <p class="font-medium text-slate-700">
                                        No recent stock movements
                                    </p>

                                    <p class="mt-1 text-sm text-muted">
                                        Inventory movements will appear here
                                        when stock changes occur.
                                    </p>
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

        {{-- Recent Purchases --}}
        <section>

            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900">
                    Recent purchases
                </h2>

                <p class="mt-1 text-sm text-muted">
                    Latest purchase orders recorded in the system.
                </p>
            </div>

            <div class="app-table-container">

                <table class="app-table">

                    <caption class="sr-only">
                        Recent purchase orders
                    </caption>

                    <thead>
                        <tr>
                            <th>Purchase</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Warehouse</th>
                            <th>Status</th>
                            <th class="text-right">Document total</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($data['purchases'] as $order)

                            @php
                                $statusVariant = match ($order->status->value) {
                                    'received' => 'success',
                                    'partially_received' => 'warning',
                                    'ordered' => 'primary',
                                    'cancelled' => 'danger',
                                    'draft' => 'neutral',
                                    default => 'neutral',
                                };
                            @endphp

                            <tr>

                                <td>
                                    <a
                                        href="{{ route('purchase-orders.show', $order) }}"
                                        class="font-medium text-primary-600 transition hover:text-primary-700 hover:underline"
                                    >
                                        {{ $order->number }}
                                    </a>
                                </td>

                                <td class="whitespace-nowrap text-muted">
                                    {{ $order->ordered_at?->format('Y-m-d') ?? '—' }}
                                </td>

                                <td>
                                    <span class="font-medium text-slate-900">
                                        {{ $order->supplier->name }}
                                    </span>
                                </td>

                                <td>
                                    {{ $order->warehouse->name }}
                                </td>

                                <td>
                                    <x-ui.badge :variant="$statusVariant">
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            ucfirst($order->status->value)
                                        ) }}
                                    </x-ui.badge>
                                </td>

                                <td class="text-right font-medium tabular-nums text-slate-900">
                                    <x-money :amount="$order->total" />
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="6"
                                    class="px-4 py-12 text-center"
                                >
                                    <p class="font-medium text-slate-700">
                                        No recent purchases
                                    </p>

                                    <p class="mt-1 text-sm text-muted">
                                        Recent purchase orders will appear here.
                                    </p>
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

        <p class="mt-4 text-xs leading-5 text-muted">
            Latest 10 movements and 10 purchase documents.
            Category filters select matching purchase documents;
            displayed totals remain whole-document totals.
        </p>

    @endif

    {{-- Livewire loading indicator --}}
    <div
        wire:loading
        role="status"
        class="fixed bottom-5 right-5 z-50 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-slate-600 shadow-lg"
    >
        Updating dashboard…
    </div>

</div>