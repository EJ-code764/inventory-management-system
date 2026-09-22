@can('viewAny', App\Models\Product::class)
    <a href="{{ route('products.index') }}" @class(['mt-2 block rounded-lg px-3 py-2 text-sm font-medium', 'bg-slate-700' => request()->routeIs('products.*')])>Products</a>
@endcan
