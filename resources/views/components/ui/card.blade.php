@props([
    'padding' => true,
])

<div
    {{ $attributes->class([
        'rounded-xl border border-border bg-surface shadow-sm',
        'p-5' => $padding,
    ]) }}
>
    {{ $slot }}
</div>