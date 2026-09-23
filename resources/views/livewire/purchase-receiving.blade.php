<form wire:submit="receive"
    wire:confirm="Record this receipt and increase stock? This action cannot be edited afterwards." class="space-y-6">
    <x-alert />
    <p>Receiving into <strong>{{ $order->warehouse->name }}</strong>. Leave a quantity blank to skip that line.</p>
    <p class="text-sm text-slate-600">A batch number is required when an expiration date is supplied. Receive separate
        lots as separate partial receipts. Existing lot numbers must retain their original unit cost and expiration
        date.</p>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-6">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3">Product / SKU</th>
                    <th class="p-3">Ordered</th>
                    <th class="p-3">Received</th>
                    <th class="p-3">Remaining</th>
                    <th class="p-3">Receive now</th>
                    <th class="p-3">Optional batch tracking</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr wire:key="receive-{{ $item->id }}" class="border-b">
                        <td class="p-3"><x-purchase-item-label :item="$item->stockItem" /></td>
                        <td class="p-3">{{ $item->ordered_quantity }}</td>
                        <td class="p-3">{{ $item->received_quantity }}</td>
                        <td class="p-3">{{ $item->remainingQuantity() }}</td>
                        <td class="p-3">
                            @if (\Brick\Math\BigDecimal::of($item->remainingQuantity())->isPositive())
                                <label for="receive-{{ $item->id }}" class="sr-only">Receive
                                    {{ $item->stockItem->sku }}</label><input id="receive-{{ $item->id }}"
                                    type="number" min="0.0001" max="{{ $item->remainingQuantity() }}" step="0.0001"
                                    wire:model="quantities.{{ $item->id }}"
                                    class="w-36 rounded-lg border border-slate-300 px-3 py-2">
                            @else<span class="text-emerald-700">Complete</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if (\Brick\Math\BigDecimal::of($item->remainingQuantity())->isPositive())
                                <label for="batch-{{ $item->id }}" class="block text-xs">Batch number
                                    (optional)</label>
                                <input id="batch-{{ $item->id }}" maxlength="255"
                                    wire:model="batchNumbers.{{ $item->id }}"
                                    class="w-44 rounded-lg border border-slate-300 px-3 py-2">
                                <label for="expiry-{{ $item->id }}" class="mt-2 block text-xs">Expiration
                                    (optional)</label>
                                <input id="expiry-{{ $item->id }}" type="date"
                                    wire:model="expirationDates.{{ $item->id }}"
                                    class="w-44 rounded-lg border border-slate-300 px-3 py-2">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div><label for="receipt-notes" class="mb-1 block text-sm font-medium">Receipt notes (optional)</label>
        <textarea id="receipt-notes" rows="3" maxlength="5000" wire:model="notes"
            class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
    </div>
    <div class="flex gap-4"><button type="submit" wire:loading.attr="disabled"
            class="rounded-lg bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Record receipt</button><a
            href="{{ route('purchase-orders.show', $order) }}" class="px-4 py-2 underline">Back to order</a></div>
    <p wire:loading role="status">Recording receipt…</p>
</form>
