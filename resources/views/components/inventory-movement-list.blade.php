@props(['movements'])
<div class="overflow-x-auto rounded-xl bg-surface shadow-sm">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Inventory movements</caption>
        <thead class="border-b bg-surface-muted">
            <tr>
                @foreach (['Time', 'SKU', 'Warehouse', 'Type', 'Change', 'Before', 'After', 'Unit cost', 'User', 'Reference', 'Remarks'] as $heading)
                    <th scope="col" class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $movement)
                <tr wire:key="movement-{{ $movement->id }}" class="border-b border-slate-100">
                    <td class="whitespace-nowrap px-4 py-3">{{ $movement->occurred_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-4 py-3">{{ $movement->stockItem->sku }}</td>
                    <td class="px-4 py-3">{{ $movement->warehouse->code }}</td>
                    <td class="px-4 py-3">{{ strtoupper($movement->type->value) }}</td>
                    <td class="px-4 py-3">{{ $movement->quantity_delta }}</td>
                    <td class="px-4 py-3">{{ $movement->quantity_before }}</td>
                    <td class="px-4 py-3">{{ $movement->quantity_after }}</td>
                    {{-- <td class="px-4 py-3">{{ $movement->unit_cost ?? 'Not recorded' }}</td> --}}
                    <td class="px-4 py-3">
                        @if ($movement->unit_cost)
                            <x-money :amount="$movement->unit_cost" />
                        @else
                            Not recorded
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $movement->performedBy?->name ?? 'Legacy / deleted user' }}</td>
                    <td class="px-4 py-3">
                        {{ $movement->reference_type ? $movement->reference_type . ' #' . $movement->reference_id : 'Not supplied' }}
                    </td>
                    <td class="px-4 py-3">{{ $movement->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="p-8 text-center text-muted">No stock movements found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
