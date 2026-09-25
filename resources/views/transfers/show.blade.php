<x-layouts.app :title="$transfer->number">
    <a href="{{ route('stock-transfers.index') }}" class="app-link text-sm">
        Back to stock transfers
    </a>

    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">
                {{ $transfer->number }}
            </h1>

            <p class="mt-1 font-medium text-foreground">
                {{ strtoupper($transfer->status->value) }}
            </p>
        </div>

        @if ($transfer->status === \App\StockTransferStatus::Draft)
            <div class="flex flex-wrap gap-3">
                @can('update', $transfer)
                    <a
                        href="{{ route('stock-transfers.edit', $transfer) }}"
                        class="btn-secondary"
                    >
                        Edit draft
                    </a>
                @endcan

                @foreach ([
                    'completed' => ['complete', 'Complete transfer'],
                    'cancelled' => ['cancel', 'Cancel transfer']
                ] as $state => [$ability, $label])

                    @can($ability, $transfer)
                        <form
                            method="POST"
                            action="{{ route('stock-transfers.status', $transfer) }}"
                            x-data
                            @submit="
                                if (!confirm(
                                    '{{ $state === 'completed'
                                        ? 'Complete this transfer and move all listed stock? This cannot be undone here.'
                                        : 'Cancel this draft transfer? No inventory will change.'
                                    }}'
                                )) $event.preventDefault()
                            "
                        >
                            @csrf
                            @method('PATCH')

                            <input
                                type="hidden"
                                name="status"
                                value="{{ $state }}"
                            >

                            <input
                                type="hidden"
                                name="revision"
                                value="{{ $transfer->revision }}"
                            >

                            <button
                                type="submit"
                                class="{{ $state === 'completed' ? 'btn-primary' : 'btn-danger' }}"
                            >
                                {{ $label }}
                            </button>
                        </form>
                    @endcan
                @endforeach
            </div>
        @endif
    </div>

    <dl class="mb-6 grid gap-4 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-3">
        @foreach ([
            'Source warehouse' => $transfer->sourceWarehouse->name,
            'Destination warehouse' => $transfer->destinationWarehouse->name,
            'Transfer date' => $transfer->transfer_date?->format('Y-m-d'),
            'Created by' => $transfer->creator->name,
            'Processed by' => $transfer->processor?->name,
            'Completed at' => $transfer->completed_at?->format('Y-m-d H:i:s')
        ] as $label => $value)

            <div>
                <dt class="text-sm text-muted">
                    {{ $label }}
                </dt>

                <dd class="mt-1 font-medium text-foreground">
                    {{ $value ?? '—' }}
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="app-table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Product / SKU</th>
                    <th>Transfer quantity</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($transfer->items as $item)
                    <tr>
                        <td>
                            <x-purchase-item-label :item="$item->stockItem" />
                        </td>

                        <td>
                            {{ $item->quantity }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($transfer->remarks)
        <div class="my-6 rounded-xl border border-border bg-surface p-6 shadow-sm">
            <p class="whitespace-pre-line text-foreground">
                {{ $transfer->remarks }}
            </p>
        </div>
    @endif

    <section class="mt-6 rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h2 class="section-title mb-3">
            Movement history
        </h2>

        <ul class="divide-y divide-border">
            @forelse ($transfer->movements as $movement)
                <li class="py-3 text-foreground">
                    {{ $movement->stockItem->sku }}
                    · {{ $movement->warehouse->code }}
                    · {{ strtoupper($movement->type->value) }}
                    · {{ $movement->quantity_delta }}
                    · {{ $movement->occurred_at->format('Y-m-d H:i:s') }}
                </li>
            @empty
                <li class="py-3 text-sm text-muted">
                    No stock has moved. Inventory changes only when this
                    transfer is completed.
                </li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>