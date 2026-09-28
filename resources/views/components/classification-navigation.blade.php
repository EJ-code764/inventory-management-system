@foreach ([
    'categories' => \App\Models\Category::class,
    'brands' => \App\Models\Brand::class,
    'units' => \App\Models\Unit::class,
] as $resource => $model)

    @can('viewAny', [$model, $resource])
        <a
            href="{{ route($resource . '.index') }}"
            @if (request()->routeIs($resource . '.*')) aria-current="page" @endif
            @class([
                'block rounded-lg px-3 py-2 text-sm font-medium transition-colors',

                'bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300'
                    => request()->routeIs($resource . '.*'),

                'text-muted hover:bg-surface-muted hover:text-foreground'
                    => ! request()->routeIs($resource . '.*'),
            ])
        >
            {{ ucfirst($resource) }}
        </a>
    @endcan
@endforeach