<x-layouts.app title="Activity logs">
    <h1 class="mb-2 text-2xl font-semibold">Activity logs</h1>
    <p class="mb-6 text-sm text-muted">Read-only activity history from the time logging was enabled. Values are allowlisted; credentials and free-text notes are excluded. Unattributed console/system operations have no user or IP.</p>
    <livewire:activity-log-table />
</x-layouts.app>
