@foreach (['categories' => \App\Models\Category::class, 'brands' => \App\Models\Brand::class, 'units' => \App\Models\Unit::class] as $resource => $model)
    @can('viewAny', [$model, $resource])
        <a href="{{ route($resource.'.index') }}" @if(request()->routeIs($resource.'.*')) aria-current="page" @endif class="block rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-700 hover:text-white {{ request()->routeIs($resource.'.*') ? 'bg-slate-700 text-white' : '' }}">{{ ucfirst($resource) }}</a>
    @endcan
@endforeach
