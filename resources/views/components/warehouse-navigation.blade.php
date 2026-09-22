@can('viewAny', App\Models\Warehouse::class)
    <a href="{{ route('warehouses.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('warehouses.*')])>Warehouses</a>
@endcan
