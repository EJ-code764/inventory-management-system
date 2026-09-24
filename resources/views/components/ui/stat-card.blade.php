@props([
    'label',
    'value' => null,
    'description' => null,
])

<div
    {{ $attributes->class(
        'rounded-xl border border-slate-200 bg-white p-5 shadow-sm'
    ) }}
>
    <p class="text-sm font-medium text-slate-500">
        {{ $label }}
    </p>

    <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
        {{ $value ?? $slot }}
    </div>

    @if ($description)
        <p class="mt-1 text-xs text-slate-500">
            {{ $description }}
        </p>
    @endif
</div>