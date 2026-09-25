<x-layouts.app :title="$warehouse->exists ? 'Edit warehouse' : 'Add warehouse'">

    <h1 class="page-title mb-6">
        {{ $warehouse->exists ? 'Edit warehouse' : 'Add warehouse' }}
    </h1>

    <form
        method="POST"
        action="{{ $warehouse->exists
            ? route('warehouses.update', $warehouse)
            : route('warehouses.store') }}"
        class="max-w-2xl space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm"
    >
        @csrf

        @if ($warehouse->exists)
            @method('PUT')
        @endif

        <x-classification-field
            name="code"
            label="Warehouse code"
            :value="$warehouse->code"
            required
            maxlength="255"
            placeholder="MAIN, BR001, BR002"
        />

        <x-classification-field
            name="name"
            label="Warehouse name"
            :value="$warehouse->name"
            required
            maxlength="255"
        />

        <div>
            <label for="address" class="form-label">
                Address (optional)
            </label>

            <textarea
                id="address"
                name="address"
                rows="3"
                maxlength="255"
                class="form-input"
                aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"
                aria-describedby="address-error"
            >{{ old('address', $warehouse->address) }}</textarea>

            @error('address')
                <p id="address-error" class="mt-1 text-sm text-red-700 dark:text-red-400">
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="status" class="form-label">
                Status
            </label>

            <select
                id="status"
                name="status"
                required
                class="form-input"
                aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}"
                aria-describedby="status-error"
            >
                <option
                    value="active"
                    @selected(old('status', $warehouse->status) === 'active')
                >
                    Active
                </option>

                <option
                    value="inactive"
                    @selected(old('status', $warehouse->status) === 'inactive')
                >
                    Inactive
                </option>
            </select>

            @error('status')
                <p id="status-error" class="mt-1 text-sm text-red-700 dark:text-red-400">
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div class="flex gap-4">
            <button type="submit" class="btn-primary">
                Save warehouse
            </button>

            <a
                class="btn-secondary"
                href="{{ $warehouse->exists
                    ? route('warehouses.show', $warehouse)
                    : route('warehouses.index') }}"
            >
                Cancel
            </a>
        </div>

    </form>

</x-layouts.app>