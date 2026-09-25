<form wire:submit="save"
    wire:confirm="Apply this physical count and record an inventory movement? This adjustment cannot be edited or deleted."
    class="max-w-5xl space-y-6">
    <x-alert />
    <p class="text-sm text-muted">Enter the new physical stock count, not the amount to add or remove. A reason is
        required. Reserved stock cannot be consumed.</p>
    <div class="rounded-xl border border-border bg-surface p-6">
        <label for="adjust-warehouse" class="mb-1 block text-sm font-medium">Warehouse</label>
        <select id="adjust-warehouse" wire:model.live="warehouseId" required
            class="w-full rounded-lg border border-border px-3 py-2">
            <option value="">Select a warehouse</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
            @endforeach
        </select>
        <label for="adjust-search" class="mb-1 mt-5 block text-sm font-medium">Find a product or variant</label>
        <input id="adjust-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255"
            placeholder="Product, variant, SKU or barcode" class="w-full rounded-lg border border-border px-3 py-2">
        <p class="my-2 text-xs text-muted">Select a warehouse first. Showing up to 15 active matches.</p>
        <ul class="max-h-56 divide-y overflow-y-auto">
            @forelse($matches as $item)
                <li wire:key="adjust-choice-{{ $item->id }}" class="flex items-center justify-between gap-4 py-2">
                    <div><x-purchase-item-label :item="$item" /></div><button type="button"
                        wire:click="selectItem({{ $item->id }})" wire:loading.attr="disabled"
                        @disabled($warehouseId === '')
                        class="rounded-lg border border-border px-3 py-1 disabled:opacity-50">Select</button>
            </li>@empty<li class="py-4 text-muted">No active products match.</li>
            @endforelse
        </ul>
    </div>
    @if ($currentQuantity !== null && $selectedItem)
        <section class="space-y-5 rounded-xl border border-border bg-surface p-6">
            <h2 class="text-lg font-semibold">Physical count</h2>
            <div><span class="mb-1 block text-sm text-muted">Product / variant</span><x-purchase-item-label
                    :item="$selectedItem" /></div>
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-muted">Current stock</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ $currentQuantity }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Reserved stock</dt>
                    <dd class="text-xl tabular-nums">{{ $reservedQuantity }}</dd>
                </div>
                <div aria-live="polite">
                    <dt class="text-sm text-muted">Difference</dt>
                    <dd class="text-xl font-semibold tabular-nums">{{ $difference ?? '—' }}</dd>
                </div>
            </dl>
            <button type="button" wire:click="refreshStock" class="text-sm underline">Refresh current stock and
                re-enter count</button>
            <div><label for="adjust-new" class="mb-1 block text-sm font-medium">New stock</label><input id="adjust-new"
                    type="number" min="0" step="0.0001" required wire:model.live.debounce.300ms="newQuantity"
                    aria-invalid="{{ $errors->has('new_quantity') ? 'true' : 'false' }}"
                    aria-describedby="adjust-new-error" class="w-full rounded-lg border border-border px-3 py-2">
                @error('new_quantity')
                    <p id="adjust-new-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            @if ($difference !== null && !\Brick\Math\BigDecimal::of($difference)->isZero())
                <p class="text-sm" aria-live="polite">Movement:
                    <strong>{{ \Brick\Math\BigDecimal::of($difference)->isNegative() ? 'ADJUSTMENT_OUT' : 'ADJUSTMENT_IN' }}</strong>
                    · Quantity: {{ \Brick\Math\BigDecimal::of($difference)->abs() }}
                </p>
            @endif
            <div><label for="adjust-reason" class="mb-1 block text-sm font-medium">Reason</label>
                <textarea id="adjust-reason" required maxlength="255" rows="3" wire:model="reason"
                    placeholder="For example: physical count correction, damaged goods, expired goods"
                    aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" aria-describedby="adjust-reason-error"
                    class="w-full rounded-lg border border-border px-3 py-2"></textarea>
                @error('reason')
                    <p id="adjust-reason-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </section>
    @endif
    <div class="flex gap-4"><button type="submit" wire:loading.attr="disabled" @disabled($currentQuantity === null)
            class="btn-primary">Record adjustment</button><a
            href="{{ route('stock-adjustments.index') }}" class="px-4 py-2 underline">Cancel</a></div>
    <p wire:loading role="status" class="text-sm">Working…</p>
</form>
