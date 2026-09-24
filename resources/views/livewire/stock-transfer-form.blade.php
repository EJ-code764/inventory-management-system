<form wire:submit="save" class="space-y-6">
    <x-alert />
    <div class="grid gap-5 rounded-xl border border-border bg-surface p-6 sm:grid-cols-2">
        @foreach (['source_warehouse_id' => 'Source warehouse', 'destination_warehouse_id' => 'Destination warehouse'] as $field => $label)
            <div><label for="transfer-{{ $field }}"
                    class="mb-1 block text-sm font-medium">{{ $label }}</label><select
                    id="transfer-{{ $field }}" wire:model.live="form.{{ $field }}" required
                    class="w-full rounded-lg border border-border px-3 py-2">
                    <option value="">Select active warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <div><label for="transfer-date" class="mb-1 block text-sm font-medium">Transfer date</label><input
                id="transfer-date" type="date" required wire:model="form.transfer_date"
                class="w-full rounded-lg border border-border px-3 py-2"></div>
    </div>
    <section class="rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-3 text-lg font-semibold">Add products or variants</h2>
        <label for="transfer-search" class="sr-only">Search product, SKU or barcode</label><input id="transfer-search"
            type="search" wire:model.live.debounce.300ms="search" maxlength="255"
            placeholder="Search product, variant, SKU or barcode"
            class="w-full rounded-lg border border-border px-3 py-2">
        <p class="my-2 text-xs text-muted">Showing up to 15 active matches. Availability is a preview and is
            rechecked when completing the transfer.</p>
        <ul class="max-h-60 divide-y overflow-y-auto">
            @forelse($matches as $item)
                <li wire:key="transfer-choice-{{ $item->id }}" class="flex items-center justify-between gap-4 py-2">
                    <div><x-purchase-item-label :item="$item" /><span class="text-xs text-muted">Available at
                            source: {{ $balances->get($item->id)?->availableQuantity() ?? '0.0000' }}</span></div>
                    <button type="button" wire:click="addItem({{ $item->id }})" wire:loading.attr="disabled"
                        class="rounded-lg border border-border px-3 py-1">Add</button>
            </li>@empty<li class="py-4 text-muted">No active products match.</li>
            @endforelse
        </ul>
    </section>
    <section class="overflow-x-auto rounded-xl border border-border bg-surface p-6">
        <h2 class="mb-3 text-lg font-semibold">Transfer items</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3">Product / SKU</th>
                    <th class="p-3">On hand</th>
                    <th class="p-3">Reserved</th>
                    <th class="p-3">Available</th>
                    <th class="p-3">Transfer quantity</th>
                    <th class="p-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($form['items'] as $index => $line)
                    @php($balance = $balances->get($line['stock_item_id']))
                    <tr wire:key="transfer-line-{{ $index }}-{{ $line['stock_item_id'] }}" class="border-b">
                        <td class="p-3"><x-purchase-item-label :item="$stockItems->get($line['stock_item_id'])" /></td>
                        <td class="p-3">{{ $balance?->quantity ?? '0.0000' }}</td>
                        <td class="p-3">{{ $balance?->reserved_quantity ?? '0.0000' }}</td>
                        <td class="p-3">{{ $balance?->availableQuantity() ?? '0.0000' }}</td>
                        <td class="p-3"><label for="transfer-qty-{{ $index }}" class="sr-only">Quantity for
                                line {{ $index + 1 }}</label><input id="transfer-qty-{{ $index }}"
                                type="number" min="0.0001" step="0.0001" required
                                wire:model="form.items.{{ $index }}.quantity"
                                class="w-36 rounded-lg border border-border px-3 py-2"></td>
                        <td class="p-3"><button type="button" wire:click="removeItem({{ $index }})"
                                class="text-red-700 underline">Remove</button></td>
                    </tr>
                @empty<tr>
                        <td colspan="6" class="p-6 text-center text-muted">Add at least one product.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div><label for="transfer-remarks" class="mb-1 block text-sm font-medium">Remarks (optional)</label>
        <textarea id="transfer-remarks" rows="3" maxlength="5000" wire:model="form.remarks"
            class="w-full rounded-lg border border-border px-3 py-2"></textarea>
    </div>
    <div class="flex gap-4"><button type="submit" wire:loading.attr="disabled"
            class="rounded-lg bg-surface-muted px-4 py-2 text-white disabled:opacity-50">Save draft</button><a
            href="{{ route('stock-transfers.index') }}" class="px-4 py-2 underline">Cancel</a></div>
    <p class="text-sm text-muted">Drafts do not move or reserve inventory. Completion moves all items atomically.
    </p>
    <p wire:loading role="status">Working…</p>
</form>
