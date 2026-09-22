<x-layouts.app title="Stock adjustment">
    <a href="{{ route('stock-adjustments.index') }}" class="text-sm underline">Back to stock adjustments</a>
    <h1 class="my-6 break-all text-2xl font-semibold">{{ $adjustment->number }}</h1>
    <dl class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
        @foreach(['Warehouse' => $adjustment->warehouse->name, 'Status' => strtoupper($adjustment->status->value), 'Recorded by' => $adjustment->creator->name, 'Recorded at' => $adjustment->created_at?->format('Y-m-d H:i:s')] as $label => $value)<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>@endforeach
    </dl>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-6"><table class="w-full text-left text-sm"><thead><tr class="border-b">@foreach(['Product / SKU', 'Previous stock', 'New stock', 'Difference', 'Reason'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead><tbody>
        @foreach($adjustment->items as $line)<tr class="border-b"><td class="p-3"><x-purchase-item-label :item="$line->stockItem" /></td><td class="p-3">{{ $line->previous_quantity }}</td><td class="p-3">{{ $line->new_quantity }}</td><td class="p-3">{{ $line->difference }}</td><td class="p-3">{{ $line->reason }}</td></tr>@endforeach
    </tbody></table></div>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6"><h2 class="mb-3 text-lg font-semibold">Inventory movements</h2><ul>
        @foreach($adjustment->movements as $movement)<li class="py-2">{{ strtoupper($movement->type->value) }} · Quantity {{ \Brick\Math\BigDecimal::of($movement->quantity_delta)->abs() }} · {{ $movement->occurred_at->format('Y-m-d H:i:s') }}</li>@endforeach
    </ul></section>
    <p class="mt-6 text-sm text-slate-600">Adjustments are immutable. Any further correction must be recorded as a new adjustment with its own reason.</p>
</x-layouts.app>
