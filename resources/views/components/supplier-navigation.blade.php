@can('viewAny', App\Models\Supplier::class)
    <a href="{{ route('suppliers.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('suppliers.*')])>Suppliers</a>
@endcan
