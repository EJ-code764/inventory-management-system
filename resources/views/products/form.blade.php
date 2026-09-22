<x-layouts.app :title="$product->exists ? 'Edit product' : 'Add product'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $product->exists ? 'Edit product' : 'Add product' }}</h1>
    @if($categories->isEmpty() || $units->isEmpty())
        <p role="alert" class="mb-4 rounded-lg bg-amber-100 p-4">Create an active category and unit before adding a product.</p>
    @endif
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="max-w-4xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
        @csrf
        @if($product->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-classification-field name="name" label="Product name" :value="$product->name" required maxlength="255" />
            <x-classification-field name="sku" label="SKU" :value="$product->stockItem?->sku" required maxlength="255" autocomplete="off" />
            <x-classification-field name="barcode" label="Barcode (optional)" :value="$product->stockItem?->barcode" maxlength="255" autocomplete="off" />
            @foreach(['category_id' => ['Category', $categories], 'brand_id' => ['Brand (optional)', $brands], 'unit_id' => ['Unit', $units]] as $field => [$label, $options])
                <div>
                    <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" @required($field !== 'brand_id') class="w-full rounded-lg border border-slate-300 px-3 py-2" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $field }}-error">
                        <option value="">{{ $field === 'brand_id' ? 'No brand' : 'Select '.$label }}</option>
                        @foreach($options as $option)
                            <option value="{{ $option->id }}" @selected((string) old($field, $product->{$field}) === (string) $option->id)>{{ $option->name }}{{ $option->status !== 'active' ? ' (inactive)' : '' }}</option>
                        @endforeach
                    </select>
                    @error($field) <p id="{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endforeach
            @foreach(['cost_price' => 'Cost price', 'selling_price' => 'Selling price', 'reorder_level' => 'Reorder level'] as $field => $label)
                <x-classification-field :name="$field" :label="$label" :value="$product->stockItem?->{$field} ?? '0.0000'" type="number" min="0" step="0.0001" required />
            @endforeach
            <div>
                <label for="status" class="mb-1 block text-sm font-medium">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @foreach(App\ProductStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $product->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="description" class="mb-1 block text-sm font-medium">Description (optional)</label>
            <textarea id="description" name="description" maxlength="5000" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description', $product->description) }}</textarea>
            @error('description') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <p class="text-sm text-slate-500">Reorder level is a threshold, not stock quantity. Inventory is managed separately.</p>
        <div class="flex gap-4">
            <button type="submit" @disabled($categories->isEmpty() || $units->isEmpty()) class="rounded-lg bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Save product</button>
            <a class="px-4 py-2" href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}">Cancel</a>
        </div>
    </form>
</x-layouts.app>
