<x-layouts.app :title="($record->exists ? 'Edit ' : 'Create ').str($resource)->singular()->title()">
    <div class="mb-6">
        <a href="{{ route($resource.'.index') }}" class="text-sm text-muted underline">Back to {{ $resource }}</a>
        <h1 class="mt-3 text-2xl font-semibold">{{ $record->exists ? 'Edit' : 'Create' }} {{ str($resource)->singular() }}</h1>
    </div>
    <form method="POST" action="{{ $record->exists ? route($resource.'.update', ['record' => $record->id]) : route($resource.'.store') }}" class="max-w-2xl space-y-5 rounded-xl border border-border bg-surface p-6 shadow-sm">
        @csrf
        @if ($record->exists) @method('PUT') @endif
        <x-classification-field name="name" label="Name" :value="$record->name" required maxlength="255" />
        @if ($resource === 'units')
            <x-classification-field name="short_name" label="Short name" :value="$record->short_name" required maxlength="32" />
        @else
            <div>
                <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2" aria-describedby="description-error">{{ old('description', $record->description) }}</textarea>
                @error('description') <p id="description-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        @endif
        <div>
            <label for="status" class="mb-1 block text-sm font-medium">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-border px-3 py-2" aria-describedby="status-error">
                <option value="active" @selected(old('status', $record->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $record->status) === 'inactive')>Inactive</option>
            </select>
            @error('status') <p id="status-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-4">
            <button class="rounded-lg bg-surface-muted px-4 py-2 font-medium text-white">Save {{ str($resource)->singular() }}</button>
            <a href="{{ route($resource.'.index') }}" class="text-sm underline">Cancel</a>
        </div>
    </form>
</x-layouts.app>
