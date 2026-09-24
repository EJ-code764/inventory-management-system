@props([
    'label',
    'value' => null,
    'description' => null,
])

<div
    {{ $attributes->class(
        'rrounded-xl border border-border bg-surface p-5 shadow-sm'
    ) }}
>
    <p class="text-sm font-medium text-muted">
        {{ $label }}
    </p>

    <div class="mt-2 text-2xl font-semibold tracking-tight text-foreground">
        {{ $value ?? $slot }}
    </div>

    @if ($description)
        <p class="mt-1 text-xs text-muted">
            {{ $description }}
        </p>
    @endif
</div>