<x-layouts.app :title="$product->name">
    <a
        class="app-link text-sm"
        href="{{ route('products.index') }}"
    >
        Back to products
    </a>

    <div class="my-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="page-title">
            {{ $product->name }}
        </h1>

        <div class="flex flex-wrap gap-3">
            @if ($product->stockItem)
                @can('viewAny', App\Models\Inventory::class)
                    <a
                        class="btn-secondary"
                        href="{{ route('inventory.show', $product->stockItem) }}"
                    >
                        View inventory
                    </a>
                @endcan
            @endif

            @can('update', $product)
                <a
                    class="btn-primary"
                    href="{{ route('products.edit', $product) }}"
                >
                    Edit product
                </a>
            @endcan

            <x-product-status-action :product="$product" />
        </div>
    </div>

    <dl class="app-card grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'SKU' => $product->stockItem?->sku,
            'Barcode' => $product->stockItem?->barcode,
            'Category' => $product->category?->name,
            'Brand' => $product->brand?->name,
            'Unit' => $product->unit?->name,
            'Status' => ucfirst($product->status->value),
            'Cost price' => $product->stockItem?->cost_price,
            'Selling price' => $product->stockItem?->selling_price,
            'Reorder level' => $product->stockItem?->reorder_level,
        ] as $label => $value)
            <div>
                <dt class="text-sm text-muted">
                    {{ $label }}
                </dt>

                <dd class="mt-1 break-words font-medium text-foreground">
                    {{ $value ?? 'Not set' }}
                </dd>
            </div>
        @endforeach

        <div class="sm:col-span-2 lg:col-span-3">
            <dt class="text-sm text-muted">
                Description
            </dt>

            <dd class="mt-1 whitespace-pre-wrap break-words text-foreground">
                {{ $product->description ?: 'No description' }}
            </dd>
        </div>
    </dl>
</x-layouts.app>