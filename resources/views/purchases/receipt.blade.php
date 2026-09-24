<x-layouts.app :title="$receipt->number">
    <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}" class="text-sm underline">Back to
        {{ $receipt->purchaseOrder->number }}</a>
    <h1 class="my-6 text-2xl font-semibold">Receipt {{ $receipt->number }}</h1>
    <p class="mb-6">Received by {{ $receipt->receiver->name }} at {{ $receipt->received_at->format('Y-m-d H:i') }} into
        {{ $receipt->warehouse->name }}.</p>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface p-6">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3">Product / SKU</th>
                    <th class="p-3">Received quantity</th>
                    <th class="p-3">Unit cost</th>
                    <th class="p-3">Batch</th>
                    <th class="p-3">Expiration</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt->items as $item)
                    <tr class="border-b">
                        <td class="p-3"><x-purchase-item-label :item="$item->stockItem" /></td>
                        <td class="p-3">{{ $item->quantity }}</td>
                        <td class="p-3">
                            <x-money :amount="$item->unit_cost" />
                        </td>
                        <td class="p-3">{{ $item->batch_number ?? 'Untracked' }}</td>
                        <td class="p-3">{{ $item->expiration_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($receipt->notes)
        <p class="mt-6 whitespace-pre-line">{{ $receipt->notes }}</p>
    @endif
    <p class="mt-6 text-sm text-muted">This receipt is immutable. Each received line created a PURCHASE inventory
        movement linked to this receipt.</p>
</x-layouts.app>
