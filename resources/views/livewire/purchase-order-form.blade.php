<form wire:submit="save" class="space-y-6">
    <x-alert />
    <div class="grid gap-5 rounded-xl border border-border bg-surface p-6 sm:grid-cols-2">
        <div><label for="purchase-supplier" class="mb-1 block text-sm font-medium">Supplier</label>
            <select id="purchase-supplier" wire:model="form.supplier_id" required
                class="w-full rounded-lg border border-border px-3 py-2">
                <option value="">Select active supplier</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->supplier_code }} — {{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="purchase-warehouse" class="mb-1 block text-sm font-medium">Receiving warehouse</label>
            <select id="purchase-warehouse" wire:model="form.warehouse_id" required
                class="w-full rounded-lg border border-border px-3 py-2">
                <option value="">Select active warehouse</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="order-date" class="mb-1 block text-sm font-medium">Order date</label><input id="order-date"
                type="date" required wire:model="form.ordered_at"
                class="w-full rounded-lg border border-border px-3 py-2"></div>
        <div><label for="expected-date" class="mb-1 block text-sm font-medium">Expected date (optional)</label><input
                id="expected-date" type="date" wire:model="form.expected_at"
                class="w-full rounded-lg border border-border px-3 py-2"></div>
    </div>
    <section class="rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-3 text-lg font-semibold">Add products or variants</h2>
        <label for="stock-search" class="sr-only">Search products, SKU or barcode</label>
        <input id="stock-search" type="search" wire:model.live.debounce.300ms="search"
            placeholder="Search product, variant, SKU or barcode" maxlength="255"
            class="w-full rounded-lg border border-border px-3 py-2">
        <p class="my-2 text-xs text-muted">Showing up to 15 active matches. Refine your search to find a specific
            SKU.</p>
        <ul class="max-h-60 divide-y overflow-y-auto">
            @forelse($matches as $item)
                <li wire:key="choice-{{ $item->id }}" class="flex items-center justify-between gap-4 py-2">
                    <div><x-purchase-item-label :item="$item" /></div><button type="button"
                        wire:click="addItem({{ $item->id }})" wire:loading.attr="disabled"
                        class="rounded-lg border border-border px-3 py-1">Add</button>
                </li>
            @empty<li class="py-4 text-muted">No active products match.</li>
            @endforelse
        </ul>
    </section>
    <section class="overflow-x-auto rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-3 text-lg font-semibold">Order items</h2>
        <p class="mb-3 text-sm text-muted">Discount and tax are amounts per line, not percentages. Prices and
            quantities support four decimal places. Line subtotals are rounded half-up to four places.</p>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Product / SKU</th>
                    <th class="p-2">Quantity</th>
                    <th class="p-2">Unit cost</th>
                    <th class="p-2">Discount</th>
                    <th class="p-2">Tax</th>
                    <th class="p-2">Subtotal</th>
                    <th class="p-2"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($form['items'] as $index => $line)
                    <tr wire:key="line-{{ $index }}-{{ $line['stock_item_id'] }}" class="border-b">
                        <td class="min-w-40 p-2"><x-purchase-item-label :item="$stockItems->get($line['stock_item_id'])" /></td>
                        @foreach (['ordered_quantity' => 'Quantity', 'unit_cost' => 'Unit cost', 'discount' => 'Discount', 'tax' => 'Tax'] as $field => $label)
                            <td class="p-2"><label class="sr-only"
                                    for="line-{{ $index }}-{{ $field }}">{{ $label }} for line
                                    {{ $index + 1 }}</label><input
                                    id="line-{{ $index }}-{{ $field }}" type="number" step="0.0001"
                                    min="{{ $field === 'ordered_quantity' ? '0.0001' : '0' }}" required
                                    wire:model.live.debounce.300ms="form.items.{{ $index }}.{{ $field }}"
                                    class="w-32 rounded-lg border border-border px-2 py-2"></td>
                        @endforeach
                        <td class="p-2 tabular-nums">
                            {{-- {{ $totals['items'][$index]['subtotal'] ?? '—' }} --}}
                            <x-money :amount="$totals['items'][$index]['subtotal'] ?? '—'" />
                        </td>
                        <td class="p-2"><button type="button" wire:click="removeItem({{ $index }})"
                                class="text-red-700 underline">Remove</button></td>
                    </tr>
                @empty<tr>
                        <td colspan="7" class="p-6 text-center text-muted">Add at least one product to begin.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <dl class="ml-auto mt-5 max-w-xs space-y-2" aria-live="polite">
            @foreach (['subtotal' => 'Subtotal', 'discount' => 'Discount', 'tax' => 'Tax', 'total' => 'Total'] as $field => $label)
                <div class="flex justify-between gap-6">
                    <dt>{{ $label }}</dt>
                    <dd class="font-semibold tabular-nums">
                        <x-money :amount="$totals[$field] ?? '—'" />
                    </dd>
                </div>
            @endforeach
        </dl>
        @if (!$totals && count($form['items']))
            <p class="mt-3 text-sm text-amber-800">Enter valid quantities and amounts to calculate totals. Discounts
                cannot exceed line subtotals.</p>
        @endif
    </section>
    <div><label for="purchase-notes" class="mb-1 block text-sm font-medium">Notes (optional)</label>
        <textarea id="purchase-notes" wire:model="form.notes" maxlength="5000" rows="3"
            class="w-full rounded-lg border border-border px-3 py-2"></textarea>
    </div>
    <div class="flex items-center gap-4"><button type="submit" wire:loading.attr="disabled"
            class="rounded-lg bg-surface-muted px-4 py-2 text-white disabled:opacity-50">Save draft</button><a
            href="{{ route('purchase-orders.index') }}" class="underline">Cancel</a><span wire:loading role="status"
            class="text-sm">Working…</span></div>
    <p class="text-sm text-muted">Saving or ordering does not change inventory.</p>
</form>
