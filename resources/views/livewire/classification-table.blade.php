<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-semibold">{{ ucfirst($resource) }}</h1><p class="mt-1 text-sm text-slate-600">Manage product {{ $resource }} and their availability.</p></div>
        @can('create', [$model, $resource])
            <a href="{{ route($resource.'.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Add {{ str($resource)->singular() }}</a>
        @endcan
    </div>
    <label for="classification-search" class="mb-1 block text-sm font-medium">Search by name</label>
    <input id="classification-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" class="mb-4 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 sm:max-w-sm" placeholder="Search {{ $resource }}…">
    <span wire:loading role="status" class="text-sm text-slate-500">Updating…</span>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ ucfirst($resource) }}</caption>
            <thead class="bg-slate-50 text-slate-600"><tr><th scope="col" class="p-4">Name</th><th scope="col" class="p-4">{{ $resource === 'units' ? 'Short name' : 'Description' }}</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    <tr wire:key="{{ $resource }}-{{ $record->id }}">
                        <td class="p-4 font-medium"><a class="underline" href="{{ route($resource.'.show', ['record' => $record->id]) }}">{{ $record->name }}</a></td>
                        <td class="max-w-sm p-4 text-slate-600">{{ $resource === 'units' ? $record->short_name : str($record->description)->limit(100) }}</td>
                        <td class="p-4"><span class="rounded-full px-2 py-1 text-xs {{ $record->status === 'active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($record->status) }}</span></td>
                        <td class="p-4">
                            <div class="flex flex-wrap items-start gap-3">
                                @can('update', $record)
                                    <a href="{{ route($resource.'.edit', ['record' => $record->id]) }}" class="underline">Edit</a>
                                    <form method="POST" action="{{ route($resource.'.status', ['record' => $record->id]) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $record->status === 'active' ? 'inactive' : 'active' }}">
                                        <button class="underline">{{ $record->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @endcan
                                @can('delete', $record)
                                    <details>
                                        <summary class="cursor-pointer text-red-700">Delete</summary>
                                        <form method="POST" action="{{ route($resource.'.destroy', ['record' => $record->id]) }}" class="mt-2 max-w-xs space-y-2">
                                            @csrf @method('DELETE')
                                            <p>Delete {{ $record->name }} permanently? Records in use must be deactivated instead.</p>
                                            <button class="rounded border border-red-300 px-3 py-1 text-red-700">Confirm deletion</button>
                                        </form>
                                    </details>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-slate-500">No {{ $resource }} found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
</div>
