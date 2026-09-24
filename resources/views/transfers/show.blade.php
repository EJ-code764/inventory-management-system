<x-layouts.app :title="$transfer->number">
    <a href="{{ route('stock-transfers.index') }}" class="text-sm underline">Back to stock transfers</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">{{ $transfer->number }}</h1><p class="mt-1 font-medium">{{ strtoupper($transfer->status->value) }}</p></div>
        @if($transfer->status === \App\StockTransferStatus::Draft)<div class="flex flex-wrap gap-3">
            @can('update', $transfer)<a href="{{ route('stock-transfers.edit', $transfer) }}" class="rounded-lg border border-border px-4 py-2">Edit draft</a>@endcan
            @foreach(['completed' => ['complete', 'Complete transfer'], 'cancelled' => ['cancel', 'Cancel transfer']] as $state => [$ability, $label])
                @can($ability, $transfer)<form method="POST" action="{{ route('stock-transfers.status', $transfer) }}" x-data @submit="if (!confirm('{{ $state === 'completed' ? 'Complete this transfer and move all listed stock? This cannot be undone here.' : 'Cancel this draft transfer? No inventory will change.' }}')) $event.preventDefault()">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $state }}"><input type="hidden" name="revision" value="{{ $transfer->revision }}"><button class="rounded-lg bg-surface-muted px-4 py-2 text-white">{{ $label }}</button></form>@endcan
            @endforeach
        </div>@endif
    </div>
    <dl class="mb-6 grid gap-4 rounded-xl border border-border bg-surface p-6 sm:grid-cols-3">
        @foreach(['Source warehouse' => $transfer->sourceWarehouse->name, 'Destination warehouse' => $transfer->destinationWarehouse->name, 'Transfer date' => $transfer->transfer_date?->format('Y-m-d'), 'Created by' => $transfer->creator->name, 'Processed by' => $transfer->processor?->name, 'Completed at' => $transfer->completed_at?->format('Y-m-d H:i:s')] as $label => $value)<div><dt class="text-sm text-muted">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value ?? '—' }}</dd></div>@endforeach
    </dl>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface p-6"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Product / SKU</th><th class="p-3">Transfer quantity</th></tr></thead><tbody>@foreach($transfer->items as $item)<tr class="border-b"><td class="p-3"><x-purchase-item-label :item="$item->stockItem" /></td><td class="p-3">{{ $item->quantity }}</td></tr>@endforeach</tbody></table></div>
    @if($transfer->remarks)<p class="my-6 whitespace-pre-line rounded-xl bg-surface p-6">{{ $transfer->remarks }}</p>@endif
    <section class="mt-6 rounded-xl border border-border bg-surface p-6"><h2 class="mb-3 text-lg font-semibold">Movement history</h2><ul class="divide-y">
        @forelse($transfer->movements as $movement)<li class="py-3">{{ $movement->stockItem->sku }} · {{ $movement->warehouse->code }} · {{ strtoupper($movement->type->value) }} · {{ $movement->quantity_delta }} · {{ $movement->occurred_at->format('Y-m-d H:i:s') }}</li>@empty<li class="text-sm text-muted">No stock has moved. Inventory changes only when this transfer is completed.</li>@endforelse
    </ul></section>
</x-layouts.app>
