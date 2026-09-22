@can('viewAny', App\Models\Inventory::class)
    <a href="{{ route('inventory.dashboard') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('inventory.dashboard')])>Inventory dashboard</a>
    <a href="{{ route('inventory.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('inventory.index', 'inventory.show')])>Stock overview</a>
@endcan
@can('viewAny', App\Models\InventoryMovement::class)
    <a href="{{ route('inventory.movements') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('inventory.movements')])>Stock movements</a>
@endcan
@can('viewAny', App\Models\StockAdjustment::class)
    <a href="{{ route('stock-adjustments.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('stock-adjustments.*')])>Stock adjustments</a>
@endcan
@can('viewAny', App\Models\StockTransfer::class)
    <a href="{{ route('stock-transfers.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('stock-transfers.*')])>Stock transfers</a>
@endcan
@can('viewAny', App\Models\ProductBatch::class)
    <a href="{{ route('product-batches.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('product-batches.*')])>Batches & expiration</a>
@endcan
@if(collect(\App\Services\ReportQuery::TYPES)->keys()->contains(fn ($report) => auth()->user()->can('view-report', $report)))
    <a href="{{ route('reports.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('reports.*')])>Reports</a>
@endif
@can('viewAny', \App\Models\ActivityLog::class)
    <a href="{{ route('activity-logs.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('activity-logs.*')])>Activity logs</a>
@endcan
