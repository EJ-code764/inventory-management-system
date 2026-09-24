@props(['product'])
@can('update', $product)
    @php($activating = $product->status === App\ProductStatus::Inactive)
    <div x-data>
        <button type="button" class="rounded-lg border border-border px-4 py-2" x-on:click="$refs.confirm.showModal()">{{ $activating ? 'Activate' : 'Deactivate' }}</button>
        <dialog x-ref="confirm" class="m-auto w-full max-w-md rounded-xl p-6 backdrop:bg-surface-muted/50" aria-labelledby="status-title-{{ $product->id }}">
            <h2 id="status-title-{{ $product->id }}" class="text-lg font-semibold">{{ $activating ? 'Activate' : 'Deactivate' }} product?</h2>
            <p class="mt-3 text-muted">{{ $product->name }} will become {{ $activating ? 'active' : 'inactive' }}. Its history will be preserved.</p>
            <form method="POST" action="{{ route('products.status', $product) }}" class="mt-6 flex justify-end gap-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $activating ? 'active' : 'inactive' }}">
                <button type="button" class="rounded-lg border px-4 py-2" x-on:click="$refs.confirm.close()" autofocus>Cancel</button>
                <button type="submit" class="rounded-lg bg-surface-muted px-4 py-2 text-white">Confirm {{ $activating ? 'activation' : 'deactivation' }}</button>
            </form>
        </dialog>
    </div>
@endcan
