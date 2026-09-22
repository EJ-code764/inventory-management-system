<x-layouts.app :title="$title">
    <a href="{{ route('reports.index') }}" class="text-sm underline">All reports</a>
    <h1 class="my-6 text-2xl font-semibold">{{ $title }}</h1>
    <livewire:report-table :report="$report" />
</x-layouts.app>
