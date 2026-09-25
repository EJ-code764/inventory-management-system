@props(['product'])

@can('update', $product)
    @php($activating = $product->status === App\ProductStatus::Inactive)

    <div x-data>
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
            aria-labelledby="status-title-{{ $product->id }}"
        >
            <h2
                id="status-title-{{ $product->id }}"
                class="text-lg font-semibold"
            >
                {{ $activating ? 'Activate' : 'Deactivate' }} product?
            </h2>

            <p class="mt-3 text-muted">
                {{ $product->name }} will become
                {{ $activating ? 'active' : 'inactive' }}.
                Its history will be preserved.
            </p>

            <form
                method="POST"
                action="{{ route('products.status', $product) }}"
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

                <button type="submit" class="btn-primary">
                    Confirm {{ $activating ? 'activation' : 'deactivation' }}
                </button>
            </form>
        </dialog>
    </div>
@endcan