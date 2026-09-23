<div>
    <div class="mb-4 grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-3">
        <div><label for="movement-search" class="mb-1 block text-sm font-medium">Product, SKU or barcode</label><input
                id="movement-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255"
                class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="movement-warehouse" class="mb-1 block text-sm font-medium">Warehouse</label><select
                id="movement-warehouse" wire:model.live="warehouse"
                class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All warehouses</option>
                @foreach ($warehouses as $option)
                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="movement-type" class="mb-1 block text-sm font-medium">Movement type</label><select
                id="movement-type" wire:model.live="type" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">All types</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}">{{ strtoupper($option->value) }}</option>
                @endforeach
            </select></div>
    </div>
    <p wire:loading role="status" class="mb-2 text-sm text-slate-500">Updating movement history…</p>
    <x-inventory-movement-list :movements="$movements" />
    <div class="mt-4">{{ $movements->links() }}</div>
</div>
