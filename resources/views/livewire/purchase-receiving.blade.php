<form
    wire:submit="receive"
    wire:confirm="Record this receipt and increase stock? This action cannot be edited afterwards."
    class="space-y-6"
>
    <x-alert />

    <div>
        <p class="text-foreground">
            Receiving into
            <strong>{{ $order->warehouse->name }}</strong>.
            Leave a quantity blank to skip that line.
        </p>

        <p class="mt-2 text-sm text-muted">
            A batch number is required when an expiration date is supplied.
            Receive separate lots as separate partial receipts.
            Existing lot numbers must retain their original unit cost and expiration date.
        </p>
    </div>

    <div class="app-table-container">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Product / SKU</th>
                    <th>Ordered</th>
                    <th>Received</th>
                    <th>Remaining</th>
                    <th>Receive now</th>
                    <th>Optional batch tracking</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($order->items as $item)
                    <tr wire:key="receive-{{ $item->id }}">
                        <td>
                            <x-purchase-item-label :item="$item->stockItem" />
                        </td>

                        <td class="tabular-nums">
                            {{ $item->ordered_quantity }}
                        </td>

                        <td class="tabular-nums">
                            {{ $item->received_quantity }}
                        </td>

                        <td class="tabular-nums">
                            {{ $item->remainingQuantity() }}
                        </td>

                        <td>
                            @if (\Brick\Math\BigDecimal::of($item->remainingQuantity())->isPositive())
                                <label
                                    for="receive-{{ $item->id }}"
                                    class="sr-only"
                                >
                                    Receive {{ $item->stockItem->sku }}
                                </label>

                                <input
                                    id="receive-{{ $item->id }}"
                                    type="number"
                                    min="0.0001"
                                    max="{{ $item->remainingQuantity() }}"
                                    step="0.0001"
                                    wire:model="quantities.{{ $item->id }}"
                                    class="form-input w-36"
                                >
                            @else
                                <x-ui.badge variant="success">
                                    Complete
                                </x-ui.badge>
                            @endif
                        </td>

                        <td>
                            @if (\Brick\Math\BigDecimal::of($item->remainingQuantity())->isPositive())
                                <div class="space-y-3">
                                    <div>
                                        <label
                                            for="batch-{{ $item->id }}"
                                            class="form-label text-xs"
                                        >
                                            Batch number (optional)
                                        </label>

                                        <input
                                            id="batch-{{ $item->id }}"
                                            maxlength="255"
                                            wire:model="batchNumbers.{{ $item->id }}"
                                            class="form-input w-44"
                                        >
                                    </div>

                                    <div>
                                        <label
                                            for="expiry-{{ $item->id }}"
                                            class="form-label text-xs"
                                        >
                                            Expiration (optional)
                                        </label>

                                        <input
                                            id="expiry-{{ $item->id }}"
                                            type="date"
                                            wire:model="expirationDates.{{ $item->id }}"
                                            class="form-input w-44"
                                        >
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <label for="receipt-notes" class="form-label">
            Receipt notes (optional)
        </label>

        <textarea
            id="receipt-notes"
            rows="3"
            maxlength="5000"
            wire:model="notes"
            class="form-input"
        ></textarea>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="btn-primary"
        >
            Record receipt
        </button>

        <a
            href="{{ route('purchase-orders.show', $order) }}"
            class="btn-secondary"
        >
            Back to order
        </a>
    </div>

    <p
        wire:loading
        role="status"
        class="text-sm text-muted"
    >
        Recording receipt…
    </p>
</form>