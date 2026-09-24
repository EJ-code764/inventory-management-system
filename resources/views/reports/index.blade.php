<x-layouts.app title="Reports">
    <h1 class="mb-6 text-2xl font-semibold">Inventory Reports</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($reports as $key => $label)
            <a href="{{ route('reports.show', $key) }}" class="rounded-xl border border-border bg-surface p-6 font-medium hover:bg-surface-muted">{{ $label }}</a>
        @endforeach
    </div>
</x-layouts.app>
