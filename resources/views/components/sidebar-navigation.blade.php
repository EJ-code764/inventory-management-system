@php
    /*
    |--------------------------------------------------------------------------
    | Active navigation groups
    |--------------------------------------------------------------------------
    |
    | Laravel determines which group should already be open BEFORE the page
    | reaches the browser. This prevents the sidebar from flashing open/closed
    | while Alpine is initializing.
    |
    */

    $productManagementActive = request()->routeIs(
        'products.*',
        'categories.*',
        'brands.*',
        'units.*'
    );

    $classificationActive = request()->routeIs(
        'categories.*',
        'brands.*',
        'units.*'
    );

    $inventoryActive = request()->routeIs(
        'inventory.*',
        'stock-adjustments.*',
        'stock-transfers.*',
        'product-batches.*'
    );

    $purchasingActive = request()->routeIs(
        'purchase-orders.*',
        'suppliers.*'
    );
@endphp


<nav class="space-y-1" aria-label="Main navigation">

    {{-- ================================================================
        DASHBOARD
    ================================================================= --}}

    <a
        href="{{ route('dashboard') }}"
        @if(request()->routeIs('dashboard')) aria-current="page" @endif
        @class([
            'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            'bg-slate-800 text-white' => request()->routeIs('dashboard'),
            'text-slate-300 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('dashboard'),
        ])
    >
        Dashboard
    </a>


    {{-- ================================================================
        PRODUCT MANAGEMENT
    ================================================================= --}}

    <div
        x-data="{ open: @js($productManagementActive) }"
        class="pt-1"
    >

        {{-- Parent button --}}
        <button
            type="button"
            x-on:click="open = !open"
            :aria-expanded="open.toString()"
            @class([
                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold transition-colors',
                'text-white' => $productManagementActive,
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $productManagementActive,
            ])
        >
            <span>Product Management</span>

            <svg
                class="h-4 w-4 shrink-0 transition-transform duration-200"
                :class="{ 'rotate-90': open }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>


        {{-- Product Management children --}}
        <div
            x-show="open"
            @style([
                'display: none;' => ! $productManagementActive,
            ])
            class="mt-1 space-y-1"
        >

            {{-- Products --}}
            @can('viewAny', App\Models\Product::class)
                <a
                    href="{{ route('products.index') }}"
                    @if(request()->routeIs('products.*'))
                        aria-current="page"
                    @endif
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('products.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('products.*'),
                    ])
                >
                    Products
                </a>
            @endcan


            {{-- Classifications --}}
            <div
                x-data="{ classificationOpen: @js($classificationActive) }"
                class="ml-3"
            >

                <button
                    type="button"
                    x-on:click="classificationOpen = !classificationOpen"
                    :aria-expanded="classificationOpen.toString()"
                    @class([
                        'flex w-full items-center justify-between rounded-lg border-l border-slate-700 px-4 py-2 text-left text-sm transition-colors',
                        'text-white' => $classificationActive,
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! $classificationActive,
                    ])
                >
                    <span>Classifications</span>

                    <svg
                        class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
                        :class="{ 'rotate-90': classificationOpen }"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>


                {{-- Classification children --}}
                <div
                    x-show="classificationOpen"
                    @style([
                        'display: none;' => ! $classificationActive,
                    ])
                    class="mt-1 space-y-1"
                >
                    @foreach ([
                        'categories' => \App\Models\Category::class,
                        'brands' => \App\Models\Brand::class,
                        'units' => \App\Models\Unit::class,
                    ] as $resource => $model)

                        @can('viewAny', [$model, $resource])
                            <a
                                href="{{ route($resource.'.index') }}"
                                @if(request()->routeIs($resource.'.*'))
                                    aria-current="page"
                                @endif
                                @class([
                                    'ml-4 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                                    'bg-slate-800 text-white' => request()->routeIs($resource.'.*'),
                                    'text-slate-500 hover:bg-slate-800 hover:text-white' => ! request()->routeIs($resource.'.*'),
                                ])
                            >
                                {{ ucfirst($resource) }}
                            </a>
                        @endcan

                    @endforeach
                </div>

            </div>

        </div>

    </div>


    {{-- ================================================================
        INVENTORY
    ================================================================= --}}

    <div
        x-data="{ open: @js($inventoryActive) }"
        class="pt-1"
    >

        <button
            type="button"
            x-on:click="open = !open"
            :aria-expanded="open.toString()"
            @class([
                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold transition-colors',
                'text-white' => $inventoryActive,
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $inventoryActive,
            ])
        >
            <span>Inventory</span>

            <svg
                class="h-4 w-4 shrink-0 transition-transform duration-200"
                :class="{ 'rotate-90': open }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>


        {{-- Inventory children --}}
        <div
            x-show="open"
            @style([
                'display: none;' => ! $inventoryActive,
            ])
            class="mt-1 space-y-1"
        >

            @can('viewAny', App\Models\Inventory::class)

                {{-- Inventory Dashboard --}}
                <a
                    href="{{ route('inventory.dashboard') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('inventory.dashboard'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('inventory.dashboard'),
                    ])
                >
                    Inventory Dashboard
                </a>


                {{-- Stock Overview --}}
                <a
                    href="{{ route('inventory.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('inventory.index', 'inventory.show'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('inventory.index', 'inventory.show'),
                    ])
                >
                    Stock Overview
                </a>

            @endcan


            {{-- Stock Movements --}}
            @can('viewAny', App\Models\InventoryMovement::class)
                <a
                    href="{{ route('inventory.movements') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('inventory.movements'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('inventory.movements'),
                    ])
                >
                    Stock Movements
                </a>
            @endcan


            {{-- Stock Adjustments --}}
            @can('viewAny', App\Models\StockAdjustment::class)
                <a
                    href="{{ route('stock-adjustments.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('stock-adjustments.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock-adjustments.*'),
                    ])
                >
                    Stock Adjustments
                </a>
            @endcan


            {{-- Stock Transfers --}}
            @can('viewAny', App\Models\StockTransfer::class)
                <a
                    href="{{ route('stock-transfers.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('stock-transfers.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock-transfers.*'),
                    ])
                >
                    Stock Transfers
                </a>
            @endcan


            {{-- Batches --}}
            @can('viewAny', App\Models\ProductBatch::class)
                <a
                    href="{{ route('product-batches.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('product-batches.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('product-batches.*'),
                    ])
                >
                    Batches & Expiration
                </a>
            @endcan

        </div>

    </div>


    {{-- ================================================================
        PURCHASING
    ================================================================= --}}

    <div
        x-data="{ open: @js($purchasingActive) }"
        class="pt-1"
    >

        <button
            type="button"
            x-on:click="open = !open"
            :aria-expanded="open.toString()"
            @class([
                'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold transition-colors',
                'text-white' => $purchasingActive,
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $purchasingActive,
            ])
        >
            <span>Purchasing</span>

            <svg
                class="h-4 w-4 shrink-0 transition-transform duration-200"
                :class="{ 'rotate-90': open }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>


        {{-- Purchasing children --}}
        <div
            x-show="open"
            @style([
                'display: none;' => ! $purchasingActive,
            ])
            class="mt-1 space-y-1"
        >

            {{-- Purchase Orders --}}
            @can('viewAny', \App\Models\PurchaseOrder::class)
                <a
                    href="{{ route('purchase-orders.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('purchase-orders.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('purchase-orders.*'),
                    ])
                >
                    Purchase Orders
                </a>
            @endcan


            {{-- Suppliers --}}
            @can('viewAny', App\Models\Supplier::class)
                <a
                    href="{{ route('suppliers.index') }}"
                    @class([
                        'ml-3 block rounded-lg border-l border-slate-700 px-4 py-2 text-sm transition-colors',
                        'bg-slate-800 text-white' => request()->routeIs('suppliers.*'),
                        'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('suppliers.*'),
                    ])
                >
                    Suppliers
                </a>
            @endcan

        </div>

    </div>


    {{-- ================================================================
        WAREHOUSES
    ================================================================= --}}

    @can('viewAny', App\Models\Warehouse::class)
        <a
            href="{{ route('warehouses.index') }}"
            @if(request()->routeIs('warehouses.*'))
                aria-current="page"
            @endif
            @class([
                'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-slate-800 text-white' => request()->routeIs('warehouses.*'),
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('warehouses.*'),
            ])
        >
            Warehouses
        </a>
    @endcan


    {{-- ================================================================
        REPORTS
    ================================================================= --}}

    @if(
        collect(\App\Services\ReportQuery::TYPES)
            ->keys()
            ->contains(fn ($report) => auth()->user()->can('view-report', $report))
    )
        <a
            href="{{ route('reports.index') }}"
            @if(request()->routeIs('reports.*'))
                aria-current="page"
            @endif
            @class([
                'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-slate-800 text-white' => request()->routeIs('reports.*'),
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.*'),
            ])
        >
            Reports
        </a>
    @endif


    {{-- ================================================================
        ACTIVITY LOGS
    ================================================================= --}}

    @can('viewAny', \App\Models\ActivityLog::class)
        <a
            href="{{ route('activity-logs.index') }}"
            @if(request()->routeIs('activity-logs.*'))
                aria-current="page"
            @endif
            @class([
                'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-slate-800 text-white' => request()->routeIs('activity-logs.*'),
                'text-slate-300 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('activity-logs.*'),
            ])
        >
            Activity Logs
        </a>
    @endcan

</nav>