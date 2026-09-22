<x-layouts.app :title="$warehouse->name">
    <a class="text-sm underline" href="{{ route('warehouses.index') }}">Back to warehouses</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $warehouse->name }}</h1>
        @can('update', $warehouse)
            <div class="flex gap-3" x-data>
                <a class="rounded-lg bg-slate-900 px-4 py-2 text-white" href="{{ route('warehouses.edit', $warehouse) }}">Edit warehouse</a>
                @php($activating = $warehouse->status === 'inactive')
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2" x-on:click="$refs.confirm.showModal()">{{ $activating ? 'Activate' : 'Deactivate' }}</button>
                <dialog x-ref="confirm" class="m-auto w-full max-w-md rounded-xl p-6 backdrop:bg-slate-900/50" aria-labelledby="warehouse-status-title">
                    <h2 id="warehouse-status-title" class="text-lg font-semibold">{{ $activating ? 'Activate' : 'Deactivate' }} warehouse?</h2>
                    <p class="mt-3 text-slate-600">{{ $warehouse->name }} will become {{ $activating ? 'active' : 'inactive' }}. Existing records and balances will not be changed.</p>
                    <form method="POST" action="{{ route('warehouses.status', $warehouse) }}" class="mt-6 flex justify-end gap-3">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
                        <button type="button" class="rounded-lg border px-4 py-2" x-on:click="$refs.confirm.close()" autofocus>Cancel</button>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-white">Confirm {{ $activating ? 'activation' : 'deactivation' }}</button>
                    </form>
                </dialog>
            </div>
        @endcan
    </div>
    <dl class="grid gap-6 rounded-xl bg-white p-6 shadow-sm sm:grid-cols-2">
        @foreach(['Code' => $warehouse->code, 'Name' => $warehouse->name, 'Address' => $warehouse->address ?? 'Not set', 'Status' => ucfirst($warehouse->status)] as $label => $value)
            <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words font-medium">{{ $value }}</dd></div>
        @endforeach
    </dl>
</x-layouts.app>
