<div>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div><label for="batch-search" class="block text-sm font-medium">Batch, product, SKU or barcode</label><input
                id="batch-search" type="search" maxlength="255" wire:model.live.debounce.300ms="search"
                class="mt-1 w-full rounded-lg border border-border px-3 py-2"></div>
        <div><label for="batch-warehouse" class="block text-sm font-medium">Warehouse</label><select id="batch-warehouse"
                wire:model.live="warehouse" class="mt-1 w-full rounded-lg border border-border px-3 py-2">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $location)
                    <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                @endforeach
            </select></div>
        <div><label for="batch-period" class="block text-sm font-medium">Expiration report</label><select
                id="batch-period" wire:model.live="period"
                class="mt-1 w-full rounded-lg border border-border px-3 py-2">
                <option value="all">All batches (including depleted)</option>
                <option value="7">Expiring within 7 days</option>
                <option value="30">Expiring within 30 days</option>
                <option value="expired">Already expired</option>
            </select></div>
    </div>
    <p class="mb-4 text-sm text-muted">Expiration reports include positive batch balances only. Upcoming windows
        include today and the final day; already expired means before today. Expired stock is retained, never
        automatically removed.</p>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3">Batch</th>
                    <th class="p-3">Product / SKU</th>
                    <th class="p-3">Warehouse</th>
                    <th class="p-3">Quantity</th>
                    <th class="p-3">Unit cost</th>
                    <th class="p-3">Expiration</th>
                    <th class="p-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr wire:key="batch-{{ $batch->id }}" class="border-b">
                        <td class="p-3">{{ $batch->batch_number }}</td>
                        <td class="p-3"><x-purchase-item-label :item="$batch->stockItem" /></td>
                        <td class="p-3">{{ $batch->warehouse->code }}</td>
                        <td class="p-3">{{ $batch->quantity }}</td>
                        <td class="p-3">
                            <x-money :amount="$batch->unit_cost" />
                        </td>
                        <td class="p-3">{{ $batch->expiration_date?->format('Y-m-d') ?? 'Not specified' }}</td>
                        <td class="p-3">
                            {{ \Brick\Math\BigDecimal::of($batch->quantity)->isZero() ? 'Depleted' : ($batch->expiration_date?->lt(today()) ? 'Expired' : ($batch->expiration_date ? 'Dated stock' : 'No expiration')) }}
                        </td>
                    </tr>
                @empty<tr>
                        <td colspan="7" class="p-6 text-center text-muted">No batches match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $batches->links() }}</div>
    <p wire:loading role="status">Loading batches…</p>
</div>
