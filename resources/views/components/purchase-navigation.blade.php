@can('viewAny', \App\Models\PurchaseOrder::class)
    <a
        href="{{ route('purchase-orders.index') }}"
        @if (request()->routeIs('purchase-orders.*')) aria-current="page" @endif
        @class([
            'mt-2 block rounded-lg px-3 py-2 text-sm font-medium transition-colors',

            'bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300'
                => request()->routeIs('purchase-orders.*'),

            'text-muted hover:bg-surface-muted hover:text-foreground'
                => ! request()->routeIs('purchase-orders.*'),
        ])
    >
        Purchasing
    </a>
@endcan