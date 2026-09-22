<x-layouts.app :title="$supplier->name">
    <a class="text-sm underline" href="{{ route('suppliers.index') }}">Back to suppliers</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $supplier->name }}</h1>
        <div class="flex flex-wrap gap-3">
            @can('update', $supplier)
                <a class="rounded-lg bg-slate-900 px-4 py-2 text-white" href="{{ route('suppliers.edit', $supplier) }}">Edit supplier</a>
                <div x-data>
                    @php($activating = $supplier->status === 'inactive')
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2" x-on:click="$refs.confirm.showModal()">{{ $activating ? 'Activate' : 'Deactivate' }}</button>
                    <dialog x-ref="confirm" class="m-auto w-full max-w-md rounded-xl p-6 backdrop:bg-slate-900/50" aria-labelledby="supplier-status-title">
                        <h2 id="supplier-status-title" class="text-lg font-semibold">{{ $activating ? 'Activate' : 'Deactivate' }} supplier?</h2>
                        <p class="mt-3 text-slate-600">{{ $supplier->name }} will become {{ $activating ? 'active' : 'inactive' }}. Existing records will be preserved.</p>
                        <form method="POST" action="{{ route('suppliers.status', $supplier) }}" class="mt-6 flex justify-end gap-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
                            <button type="button" class="rounded-lg border px-4 py-2" x-on:click="$refs.confirm.close()" autofocus>Cancel</button>
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-white">Confirm {{ $activating ? 'activation' : 'deactivation' }}</button>
                        </form>
                    </dialog>
                </div>
            @endcan
            @can('delete', $supplier)
                <div x-data>
                    <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-red-700" x-on:click="$refs.confirm.showModal()">Delete supplier</button>
                    <dialog x-ref="confirm" class="m-auto w-full max-w-md rounded-xl p-6 backdrop:bg-slate-900/50" aria-labelledby="supplier-delete-title">
                        <h2 id="supplier-delete-title" class="text-lg font-semibold">Delete supplier?</h2>
                        <p class="mt-3 text-slate-600">Permanently delete {{ $supplier->supplier_code }} — {{ $supplier->name }}? This cannot be undone. Referenced suppliers cannot be deleted; deactivate them instead.</p>
                        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="mt-6 flex justify-end gap-3">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="rounded-lg border px-4 py-2" x-on:click="$refs.confirm.close()" autofocus>Cancel</button>
                            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-white">Confirm deletion</button>
                        </form>
                    </dialog>
                </div>
            @endcan
        </div>
    </div>
    <dl class="grid gap-6 rounded-xl bg-white p-6 shadow-sm sm:grid-cols-2">
        @foreach(['Supplier code' => $supplier->supplier_code, 'Name' => $supplier->name, 'Contact person' => $supplier->contact_person, 'Phone' => $supplier->phone, 'Email' => $supplier->email, 'Address' => $supplier->address, 'Status' => ucfirst($supplier->status)] as $label => $value)
            <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words font-medium">{{ $value ?? 'Not set' }}</dd></div>
        @endforeach
    </dl>
</x-layouts.app>
