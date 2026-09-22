@can('viewAny', \App\Models\PurchaseOrder::class)
    <a href="{{ route('purchase-orders.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-800 text-white' => request()->routeIs('purchase-orders.*'), 'hover:bg-slate-800' => ! request()->routeIs('purchase-orders.*')])>Purchasing</a>
@endcan
