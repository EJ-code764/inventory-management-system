<x-layouts.app :title="$supplier->name">
    <a class="app-link text-sm" href="{{ route('suppliers.index') }}">
        Back to suppliers
    </a>

    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="page-title">{{ $supplier->name }}</h1>

        <div class="flex flex-wrap gap-3">
            @can('update', $supplier)
                <a class="btn-primary" href="{{ route('suppliers.edit', $supplier) }}">
                    Edit supplier
                </a>

                <div x-data>
                    @php($activating = $supplier->status === 'inactive')

                    <button
                        type="button"
                        class="btn-secondary"
                        x-on:click="$refs.confirm.showModal()"
                    >
                        {{ $activating ? 'Activate' : 'Deactivate' }}
                    </button>

                    <dialog
                        x-ref="confirm"
                        class="m-auto w-full max-w-md rounded-xl border border-border bg-surface p-6 text-foreground shadow-xl backdrop:bg-black/50"
                        aria-labelledby="supplier-status-title"
                    >
                        <h2
                            id="supplier-status-title"
                            class="text-lg font-semibold"
                        >
                            {{ $activating ? 'Activate' : 'Deactivate' }} supplier?
                        </h2>

                        <p class="mt-3 text-muted">
                            {{ $supplier->name }} will become
                            {{ $activating ? 'active' : 'inactive' }}.
                            Existing records will be preserved.
                        </p>

                        <form
                            method="POST"
                            action="{{ route('suppliers.status', $supplier) }}"
                            class="mt-6 flex justify-end gap-3"
                        >
                            @csrf
                            @method('PATCH')

                            <input
                                type="hidden"
                                name="status"
                                value="{{ $activating ? 'active' : 'inactive' }}"
                            >

                            <button
                                type="button"
                                class="btn-secondary"
                                x-on:click="$refs.confirm.close()"
                                autofocus
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                class="{{ $activating ? 'btn-primary' : 'btn-danger' }}"
                            >
                                Confirm {{ $activating ? 'activation' : 'deactivation' }}
                            </button>
                        </form>
                    </dialog>
                </div>
            @endcan

            @can('delete', $supplier)
                <div x-data>
                    <button
                        type="button"
                        class="btn-danger"
                        x-on:click="$refs.confirm.showModal()"
                    >
                        Delete supplier
                    </button>

                    <dialog
                        x-ref="confirm"
                        class="m-auto w-full max-w-md rounded-xl border border-border bg-surface p-6 text-foreground shadow-xl backdrop:bg-black/50"
                        aria-labelledby="supplier-delete-title"
                    >
                        <h2
                            id="supplier-delete-title"
                            class="text-lg font-semibold"
                        >
                            Delete supplier?
                        </h2>

                        <p class="mt-3 text-muted">
                            Permanently delete {{ $supplier->supplier_code }} —
                            {{ $supplier->name }}? This cannot be undone.
                            Referenced suppliers cannot be deleted; deactivate them instead.
                        </p>

                        <form
                            method="POST"
                            action="{{ route('suppliers.destroy', $supplier) }}"
                            class="mt-6 flex justify-end gap-3"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="button"
                                class="btn-secondary"
                                x-on:click="$refs.confirm.close()"
                                autofocus
                            >
                                Cancel
                            </button>

                            <button type="submit" class="btn-danger">
                                Confirm deletion
                            </button>
                        </form>
                    </dialog>
                </div>
            @endcan
        </div>
    </div>

    <dl class="grid gap-6 rounded-xl border border-border bg-surface p-6 shadow-sm sm:grid-cols-2">
        @foreach([
            'Supplier code' => $supplier->supplier_code,
            'Name' => $supplier->name,
            'Contact person' => $supplier->contact_person,
            'Phone' => $supplier->phone,
            'Email' => $supplier->email,
            'Address' => $supplier->address,
            'Status' => ucfirst($supplier->status)
        ] as $label => $value)
            <div>
                <dt class="text-sm text-muted">
                    {{ $label }}
                </dt>

                <dd class="mt-1 whitespace-pre-wrap break-words font-medium text-foreground">
                    {{ $value ?? 'Not set' }}
                </dd>
            </div>
        @endforeach
    </dl>
</x-layouts.app>