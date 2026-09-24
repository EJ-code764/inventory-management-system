<div>
    <div class="mb-5 grid gap-4 rounded-xl bg-surface p-4 sm:grid-cols-3">
        <div><label for="supplier-search" class="mb-1 block text-sm font-medium">Search code, name or contact
                details</label><input id="supplier-search" type="search" wire:model.live.debounce.300ms="search"
                maxlength="255" class="w-full rounded-lg border border-border px-3 py-2"></div>
        <div><label for="supplier-status" class="mb-1 block text-sm font-medium">Status</label><select
                id="supplier-status" wire:model.live="status"
                class="w-full rounded-lg border border-border px-3 py-2">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select></div>
        <button type="button" wire:click="clearFilters"
            class="self-end rounded-lg border border-border px-3 py-2">Clear filters</button>
    </div>
    <p wire:loading role="status" class="mb-2 text-sm text-muted">Updating suppliers…</p>
    <div class="overflow-x-auto rounded-xl bg-surface shadow-sm" wire:loading.class="opacity-60">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Suppliers</caption>
            <thead class="border-b bg-surface-muted">
                <tr>
                    @foreach (['Code', 'Name', 'Contact person', 'Phone / email', 'Status', 'Actions'] as $heading)
                        <th scope="col" class="px-4 py-3">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $supplier->supplier_code }}</td>
                        <td class="px-4 py-3"><a class="font-medium underline"
                                href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                        <td class="px-4 py-3">{{ $supplier->contact_person ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ $supplier->phone ?? 'Not set' }}<span
                                class="block text-muted">{{ $supplier->email }}</span></td>
                        <td class="px-4 py-3">{{ ucfirst($supplier->status) }}</td>
                        <td class="px-4 py-3">
                            @can('update', $supplier)
                                <a class="underline" href="{{ route('suppliers.edit', $supplier) }}">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-muted">No suppliers match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $suppliers->links() }}</div>
</div>
