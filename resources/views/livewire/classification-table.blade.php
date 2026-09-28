<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">
                {{ ucfirst($resource) }}
            </h1>

            <p class="page-description">
                Manage product {{ $resource }} and their availability.
            </p>
        </div>

        @can('create', [$model, $resource])
            <a
                href="{{ route($resource . '.create') }}"
                class="btn-primary"
            >
                Add {{ str($resource)->singular() }}
            </a>
        @endcan
    </div>

    <div class="mb-4">
        <label for="classification-search" class="form-label">
            Search by name
        </label>

        <input
            id="classification-search"
            type="search"
            wire:model.live.debounce.300ms="search"
            maxlength="255"
            class="form-input sm:max-w-sm"
            placeholder="Search {{ $resource }}…"
        >

        <span
            wire:loading
            role="status"
            class="mt-2 block text-sm text-muted"
        >
            Updating…
        </span>
    </div>

    <div class="app-table-container">
        <table class="app-table">
            <caption class="sr-only">
                {{ ucfirst($resource) }}
            </caption>

            <thead>
                <tr>
                    <th scope="col">Name</th>

                    <th scope="col">
                        {{ $resource === 'units' ? 'Short name' : 'Description' }}
                    </th>

                    <th scope="col">Status</th>

                    <th scope="col">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($records as $record)
                    <tr wire:key="{{ $resource }}-{{ $record->id }}">
                        <td class="font-medium">
                            <a
                                class="app-link"
                                href="{{ route($resource . '.show', [
                                    'record' => $record->id
                                ]) }}"
                            >
                                {{ $record->name }}
                            </a>
                        </td>

                        <td class="max-w-sm text-muted">
                            {{ $resource === 'units'
                                ? $record->short_name
                                : str($record->description)->limit(100) }}
                        </td>

                        <td>
                            @if ($record->status === 'active')
                                <span class="badge badge-success">
                                    Active
                                </span>
                            @else
                                <span class="badge badge-neutral">
                                    {{ ucfirst($record->status) }}
                                </span>
                            @endif
                        </td>

                        <td>
                            <div class="flex flex-wrap items-start gap-3">
                                @can('update', $record)
                                    <a
                                        href="{{ route($resource . '.edit', [
                                            'record' => $record->id
                                        ]) }}"
                                        class="app-link"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route($resource . '.status', [
                                            'record' => $record->id
                                        ]) }}"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="{{ $record->status === 'active'
                                                ? 'inactive'
                                                : 'active' }}"
                                        >

                                        <button
                                            type="submit"
                                            class="app-link"
                                        >
                                            {{ $record->status === 'active'
                                                ? 'Deactivate'
                                                : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan

                                @can('delete', $record)
                                    <details>
                                        <summary
                                            class="
                                                cursor-pointer
                                                font-medium
                                                text-red-700
                                                hover:text-red-800
                                                dark:text-red-400
                                                dark:hover:text-red-300
                                            "
                                        >
                                            Delete
                                        </summary>

                                        <form
                                            method="POST"
                                            action="{{ route($resource . '.destroy', [
                                                'record' => $record->id
                                            ]) }}"
                                            class="mt-2 max-w-xs space-y-2"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <p class="text-sm text-muted">
                                                Delete {{ $record->name }} permanently?
                                                Records in use must be deactivated instead.
                                            </p>

                                            <button
                                                type="submit"
                                                class="btn-danger px-3 py-1.5"
                                            >
                                                Confirm deletion
                                            </button>
                                        </form>
                                    </details>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="4"
                            class="p-8 text-center text-muted"
                        >
                            No {{ $resource }} found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $records->links() }}
    </div>
</div>