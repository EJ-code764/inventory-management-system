<x-layouts.app :title="$order->number">
    <a href="{{ route('purchase-orders.index') }}" class="text-sm underline">Back to purchase orders</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $order->number }}</h1>
            <p class="mt-1 font-medium">{{ str_replace('_', ' ', strtoupper($order->status->value)) }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            @if ($order->status === \App\PurchaseOrderStatus::Draft)
                @can('update', $order)
                    <a href="{{ route('purchase-orders.edit', $order) }}"
                        class="btn-secondary">Edit draft</a>
                @endcan
                @can('order', $order)
                    <form method="POST" action="{{ route('purchase-orders.status', $order) }}" x-data
                        @submit="if (!confirm('Mark this purchase order as ordered? Items will no longer be editable.')) $event.preventDefault()">
                        @csrf @method('PATCH')<input type="hidden" name="status" value="ordered"><button
                            class="btn-primary">Mark ordered</button></form>
                @endcan
            @endif
            @if (in_array($order->status, [\App\PurchaseOrderStatus::Ordered, \App\PurchaseOrderStatus::PartiallyReceived], true))
                @can('receive', $order)
                    <a href="{{ route('purchase-orders.receive', $order) }}"
                        class="btn-primary">Receive products</a>
                @endcan
            @endif
            @if (in_array($order->status, [\App\PurchaseOrderStatus::Draft, \App\PurchaseOrderStatus::Ordered], true))
                @can('cancel', $order)
                    <form method="POST" action="{{ route('purchase-orders.status', $order) }}" x-data
                        @submit="if (!confirm('Cancel this unreceived purchase order?')) $event.preventDefault()">@csrf
                        @method('PATCH')<input type="hidden" name="status" value="cancelled"><button
                            class="rounded-lg border border-red-300 px-4 py-2 text-red-700">Cancel order</button></form>
                @endcan
            @endif
        </div>
    </div>
    <dl class="mb-6 grid gap-4 rounded-xl border border-border bg-surface p-6 sm:grid-cols-3">
        @foreach (['Supplier' => $order->supplier->name, 'Warehouse' => $order->warehouse->name, 'Created by' => $order->creator->name, 'Order date' => $order->ordered_at?->format('Y-m-d'), 'Expected date' => $order->expected_at?->format('Y-m-d')] as $label => $value)
            <div>
                <dt class="text-sm text-muted">{{ $label }}</dt>
                <dd class="mt-1 font-medium">{{ $value ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface p-6">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    @foreach (['Product / SKU', 'Quantity', 'Received', 'Remaining', 'Unit cost', 'Discount', 'Tax', 'Subtotal'] as $heading)
                        <th class="p-3">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b border-border">
                        <td class="p-3">
                            <x-purchase-item-label :item="$item->stockItem" />
                        </td>

                        <td class="p-3 tabular-nums">
                            {{ $item->ordered_quantity }}
                        </td>

                        <td class="p-3 tabular-nums">
                            {{ $item->received_quantity }}
                        </td>

                        <td class="p-3 tabular-nums">
                            {{ $item->remainingQuantity() }}
                        </td>

                        <td class="p-3 tabular-nums">
                            <x-money :amount="$item->unit_cost" />
                        </td>

                        <td class="p-3 tabular-nums">
                            <x-money :amount="$item->discount" />
                        </td>

                        <td class="p-3 tabular-nums">
                            <x-money :amount="$item->tax" />
                        </td>

                        <td class="p-3 tabular-nums">
                            <x-money :amount="$item->subtotal" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <dl class="ml-auto mt-5 max-w-xs space-y-2">
            @foreach (['subtotal', 'discount', 'tax', 'total'] as $field)
            <div class="flex justify-between gap-6">
                <dt>{{ ucfirst($field) }}</dt>

                <dd class="font-semibold tabular-nums">
                    <x-money :amount="$order->{$field}" />
                </dd>
            </div>
            @endforeach
        </dl>
    </div>
    @if ($order->notes)
        <p class="my-6 whitespace-pre-line rounded-xl border border-border bg-surface p-6">{{ $order->notes }}</p>
    @endif
    <section class="mt-6 rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-3 text-lg font-semibold">Receipt history</h2>
        <ul class="divide-y">
            @forelse($order->receipts as $receipt)
                <li class="flex flex-wrap justify-between gap-3 py-3"><a
                        href="{{ route('purchase-orders.receipt', [$order, $receipt]) }}"
                        class="underline">{{ $receipt->number }}</a><span>{{ $receipt->received_at->format('Y-m-d H:i') }}
                    · {{ $receipt->receiver->name }}</span></li>@empty<li class="text-sm text-muted">No
                    receipts yet. Creating or ordering this purchase does not increase inventory.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
