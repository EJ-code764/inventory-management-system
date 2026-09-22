<div>
    <x-alert />
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div><label for="activity-search" class="block text-sm font-medium">Search action, model, ID, user or values</label><input id="activity-search" type="search" maxlength="255" wire:model.live.debounce.300ms="search" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="activity-user" class="block text-sm font-medium">User</label><select id="activity-user" wire:model.live="user" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All users</option><option value="system">System / deleted user</option>@foreach($users as $actor)<option value="{{ $actor->id }}">{{ $actor->name }} (#{{ $actor->id }})</option>@endforeach</select></div>
        <div><label for="activity-action" class="block text-sm font-medium">Action</label><select id="activity-action" wire:model.live="action" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">All actions</option>@foreach($actions as $event)<option value="{{ $event }}">{{ $event }}</option>@endforeach</select></div>
        <div><label for="activity-from" class="block text-sm font-medium">From date</label><input id="activity-from" type="date" wire:model.live="from" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
        <div><label for="activity-to" class="block text-sm font-medium">To date (inclusive)</label><input id="activity-to" type="date" wire:model.live="to" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
    </div>
    <button type="button" wire:click="clearFilters" class="mb-4 text-sm underline">Clear filters</button>
    @if($logs)
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Timestamp</th><th class="p-3">User</th><th class="p-3">Action</th><th class="p-3">Model / ID</th><th class="p-3">IP address</th><th class="p-3">Values</th></tr></thead><tbody>
            @forelse($logs as $log)
                <tr wire:key="activity-{{ $log->id }}" class="border-t"><td class="whitespace-nowrap p-3">{{ $log->created_at->format('Y-m-d H:i:s') }}</td><td class="p-3">{{ $log->causer?->name ?? 'System / deleted user' }}</td><td class="p-3">{{ $log->event }}</td><td class="p-3">{{ $log->subject_type }} #{{ $log->subject_id }}</td><td class="p-3">{{ $log->ip_address ?? '—' }}</td><td class="p-3"><details><summary class="cursor-pointer">Old / new values</summary><pre class="mt-2 max-w-xl whitespace-pre-wrap break-all">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td></tr>
            @empty<tr><td colspan="6" class="p-6 text-center text-slate-500">No activity matches these filters.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $logs->links() }}</div>
    @else<p>Correct the filter errors to view activity.</p>@endif
    <p wire:loading role="status">Loading activity…</p>
</div>
