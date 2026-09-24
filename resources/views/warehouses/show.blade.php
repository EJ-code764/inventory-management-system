<x-layouts.app :title="$warehouse->name">
    <a class="text-sm underline" href="{{ route('warehouses.index') }}">Back to warehouses</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $warehouse->name }}</h1>
        @can('update', $warehouse)
            <div class="flex gap-3" x-data>
                <a class="btn-primary" href="{{ route('warehouses.edit', $warehouse) }}">Edit warehouse</a>
                @php($activating = $warehouse->status === 'inactive')
                <button type="button" class="btn-secondary" x-on:click="$refs.confirm.showModal()">{{ $activating ? 'Activate' : 'Deactivate' }}</button>
                <dialog x-ref="confirm" class="m-auto w-full max-w-md rounded-xl border border-border bg-surface p-6 text-foreground shadow-xl backdrop:bg-slate-950/50"
                    aria-labelledby="warehouse-status-title" aria-labelledby="warehouse-status-title">
                    <h2 id="warehouse-status-title" class="text-lg font-semibold">{{ $activating ? 'Activate' : 'Deactivate' }} warehouse?</h2>
                    <p class="mt-3 text-muted">{{ $warehouse->name }} will become {{ $activating ? 'active' : 'inactive' }}. Existing records and balances will not be changed.</p>
                    <form method="POST" action="{{ route('warehouses.status', $warehouse) }}" class="mt-6 flex justify-end gap-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
                        <button type="button" class="btn-secondary" x-on:click="$refs.confirm.close()" autofocus>Cancel</button>
                        <button type="submit" class="btn-primary">Confirm {{ $activating ? 'activation' : 'deactivation' }}</button>
                    </form>
                </dialog>
            </div>
        @endcan
    </div>
    <dl class="grid gap-6 rounded-xl bg-surface p-6 shadow-sm sm:grid-cols-2">
        @foreach(['Code' => $warehouse->code, 'Name' => $warehouse->name, 'Address' => $warehouse->address ?? 'Not set', 'Status' => ucfirst($warehouse->status)] as $label => $value)
            <div><dt class="text-sm text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words font-medium">{{ $value }}</dd></div>
        @endforeach
    </dl>
</x-layouts.app>
