<x-layouts.app :title="$supplier->exists ? 'Edit supplier' : 'Add supplier'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $supplier->exists ? 'Edit supplier' : 'Add supplier' }}</h1>
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="max-w-3xl space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
        @csrf
        @if($supplier->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-classification-field name="supplier_code" label="Supplier code" :value="$supplier->supplier_code" required maxlength="64" />
            <x-classification-field name="name" label="Supplier name" :value="$supplier->name" required maxlength="255" />
            <x-classification-field name="contact_person" label="Contact person (optional)" :value="$supplier->contact_person" maxlength="255" />
            <x-classification-field name="phone" label="Phone (optional)" :value="$supplier->phone" type="tel" maxlength="50" />
            <x-classification-field name="email" label="Email (optional)" :value="$supplier->email" type="email" maxlength="255" />
            <div>
                <label for="status" class="mb-1 block text-sm font-medium">Status</label>
                <select id="status" name="status" required class="w-full rounded-lg border border-border px-3 py-2" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" aria-describedby="status-error">
                    <option value="active" @selected(old('status', $supplier->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $supplier->status) === 'inactive')>Inactive</option>
                </select>
                @error('status') <p id="status-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="address" class="mb-1 block text-sm font-medium">Address (optional)</label>
            <textarea id="address" name="address" rows="3" maxlength="255" class="w-full rounded-lg border border-border px-3 py-2" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" aria-describedby="address-error">{{ old('address', $supplier->address) }}</textarea>
            @error('address') <p id="address-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-4">
            <button type="submit" class="rounded-lg bg-surface-muted px-4 py-2 text-white">Save supplier</button>
            <a class="px-4 py-2 underline" href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}">Cancel</a>
        </div>
    </form>
</x-layouts.app>
