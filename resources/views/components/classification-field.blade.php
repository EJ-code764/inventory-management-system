@props(['name', 'label', 'value' => ''])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $value) }}" {{ $attributes->class(['w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-600']) }} aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $name }}-error">
    @error($name) <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>
