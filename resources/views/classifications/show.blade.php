<x-layouts.app :title="$record->name">
    <a href="{{ route($resource.'.index') }}" class="text-sm text-muted underline">Back to {{ $resource }}</a>
    <section class="mt-4 max-w-2xl rounded-xl border border-border bg-surface p-6 shadow-sm">
        <h1 class="text-2xl font-semibold">{{ $record->name }}</h1>
        <dl class="mt-6 space-y-4">
            <div><dt class="text-sm text-muted">Status</dt><dd>{{ ucfirst($record->status) }}</dd></div>
            @if ($resource === 'units')
                <div><dt class="text-sm text-muted">Short name</dt><dd>{{ $record->short_name }}</dd></div>
            @else
                <div><dt class="text-sm text-muted">Description</dt><dd class="whitespace-pre-wrap">{{ $record->description ?: 'No description.' }}</dd></div>
            @endif
            <div><dt class="text-sm text-muted">Created</dt><dd>{{ $record->created_at->format('Y-m-d H:i') }}</dd></div>
        </dl>
        @can('update', $record)
            <a href="{{ route($resource.'.edit', ['record' => $record->id]) }}" class="mt-6 inline-block rounded-lg bg-surface-muted px-4 py-2 text-white">Edit</a>
        @endcan
    </section>
</x-layouts.app>
