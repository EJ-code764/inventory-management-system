<div>
    <div class="mb-5 rounded-xl bg-surface p-4">
        <label for="warehouse-search" class="mb-1 block text-sm font-medium">Search by name or code</label>
        <input id="warehouse-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" class="w-full rounded-lg border border-border px-3 py-2">
    </div>
    <p wire:loading role="status" class="mb-2 text-sm text-muted">Updating warehouses…</p>
    <div class="overflow-x-auto rounded-xl bg-surface shadow-sm" wire:loading.class="opacity-60">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Warehouses</caption>
            <thead class="border-b bg-surface-muted"><tr>@foreach(['Code', 'Name', 'Address', 'Status', 'Actions'] as $heading)<th scope="col" class="px-4 py-3">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse($warehouses as $warehouse)
                    <tr wire:key="warehouse-{{ $warehouse->id }}" class="border-b border-slate-100">
                        <td class="px-4 py-3">{{ $warehouse->code }}</td>
                        <td class="px-4 py-3"><a class="font-medium underline" href="{{ route('warehouses.show', $warehouse) }}">{{ $warehouse->name }}</a></td>
                        <td class="px-4 py-3">{{ $warehouse->address ?? 'Not set' }}</td>
                        <td class="px-4 py-3">{{ ucfirst($warehouse->status) }}</td>
                        <td class="px-4 py-3">@can('update', $warehouse)<a class="underline" href="{{ route('warehouses.edit', $warehouse) }}">Edit</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-muted">No warehouses match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $warehouses->links() }}</div>
</div>
