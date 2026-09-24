<x-layouts.app title="Dashboard">
    <h1 class="mb-6 text-2xl font-semibold">Inventory Dashboard</h1>
    @can('view-report', 'dashboard')
        <livewire:operations-dashboard />
    @else
        <p class="rounded-xl bg-surface p-6">Welcome to your inventory workspace. Dashboard metrics require dashboard report access. Use the navigation to access your authorized modules.</p>
    @endcan
</x-layouts.app>
